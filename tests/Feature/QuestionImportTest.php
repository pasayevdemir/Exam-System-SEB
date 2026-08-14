<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\QuestionImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploadImport(QuestionBank $bank, string $filename, string $contents, array $extra = [])
{
    return test()
        ->withSession(['admin_logged_in' => true])
        ->post(route('admin.store-imported-questions', $bank->id), array_merge([
            'file' => UploadedFile::fake()->createWithContent($filename, $contents),
        ], $extra));
}

function csvFile(array $lines): string
{
    return implode(',', QuestionImportService::CSV_HEADERS) . "\n" . implode("\n", $lines) . "\n";
}

/* -------------------------------------------------------------------------- */
/* Importing                                                                  */
/* -------------------------------------------------------------------------- */

it('imports questions from a csv into the bank', function () {
    $bank = QuestionBank::factory()->create();

    uploadImport($bank, 'questions.csv', csvFile([
        'What is 2+2?,easy,single,3,4,5,,,,2',
        'Pick the even numbers,hard,multiple,1,2,3,4,,,"2|4"',
    ]))->assertRedirect(route('admin.bank-questions', $bank->id))->assertSessionHas('success');

    $questions = Question::where('question_bank_id', $bank->id)->with('answers')->get();

    expect($questions)->toHaveCount(2);

    $first = $questions->firstWhere('question_text', 'What is 2+2?');
    expect($first->difficulty)->toBe('easy')
        ->and($first->question_type)->toBe('single')
        ->and($first->answers)->toHaveCount(3);

    // The correct flag has to land on the option the admin actually pointed at.
    $correct = $first->answers->where('is_correct', true);
    expect($correct)->toHaveCount(1)
        ->and($correct->first()->answer_text)->toBe('4');

    $second = $questions->firstWhere('question_text', 'Pick the even numbers');
    expect($second->question_type)->toBe('multiple')
        ->and($second->answers->where('is_correct', true)->pluck('answer_text')->values()->all())
        ->toBe(['2', '4']);
});

it('imports the existing json question file shape', function () {
    $bank = QuestionBank::factory()->create();

    $json = <<<'JSON'
    [
      {
        "id": 1,
        "difficulty": "easy",
        "question": "What does the HTTP 404 status code represent?",
        "variant": ["200 OK", "404 Not Found", "500 Internal Server Error", "401 Unauthorized"],
        "correct_answer": "404 Not Found"
      }
    ]
    JSON;

    uploadImport($bank, 'questions.json', $json)->assertSessionHas('success');

    $question = Question::firstWhere('question_bank_id', $bank->id);

    expect($question->question_text)->toBe('What does the HTTP 404 status code represent?')
        ->and($question->answers)->toHaveCount(4)
        ->and($question->answers->where('is_correct', true)->first()->answer_text)->toBe('404 Not Found');
});

/* -------------------------------------------------------------------------- */
/* All-or-nothing                                                             */
/* -------------------------------------------------------------------------- */

it('writes nothing when any row is invalid', function () {
    $bank = QuestionBank::factory()->create();

    uploadImport($bank, 'questions.csv', csvFile([
        'Perfectly fine,easy,single,A,B,,,,,1',
        'Broken row,easy,single,A,B,,,,,9',
    ]))->assertSessionHasErrors();

    // Not "one of two" — nothing at all.
    expect(Question::where('question_bank_id', $bank->id)->count())->toBe(0);
});

it('reports the offending row number back to the admin', function () {
    $bank = QuestionBank::factory()->create();

    $response = uploadImport($bank, 'questions.csv', csvFile([
        'Perfectly fine,easy,single,A,B,,,,,1',
        'Broken row,easy,single,A,B,,,,,9',
    ]));

    $errors = session('errors')->all();

    expect(implode(' ', $errors))->toContain('Row 3');
});

it('refuses a question that already exists in the target bank', function () {
    $bank = QuestionBank::factory()->create();
    Question::factory()->create([
        'question_bank_id' => $bank->id,
        'question_text' => 'Already there',
    ]);

    uploadImport($bank, 'questions.csv', csvFile([
        'Already there,easy,single,A,B,,,,,1',
    ]))->assertSessionHasErrors();

    expect(Question::where('question_bank_id', $bank->id)->count())->toBe(1);
});

it('skips existing questions when skip duplicates is ticked', function () {
    $bank = QuestionBank::factory()->create();
    Question::factory()->create([
        'question_bank_id' => $bank->id,
        'question_text' => 'Already there',
    ]);

    uploadImport($bank, 'questions.csv', csvFile([
        'Already there,easy,single,A,B,,,,,1',
        'Brand new,easy,single,A,B,,,,,1',
    ]), ['skip_duplicates' => '1'])->assertSessionHas('success');

    expect(Question::where('question_bank_id', $bank->id)->count())->toBe(2);
    expect(session('success'))->toContain('Skipped 1');
});

/* -------------------------------------------------------------------------- */
/* Guards                                                                     */
/* -------------------------------------------------------------------------- */

it('rejects a file upload row', function () {
    $bank = QuestionBank::factory()->create();

    uploadImport($bank, 'questions.csv', csvFile([
        'Upload your work,easy,file_upload,A,B,,,,,1',
    ]))->assertSessionHasErrors();

    expect(Question::where('question_bank_id', $bank->id)->count())->toBe(0);
});

it('rejects a file that is neither csv nor json', function () {
    $bank = QuestionBank::factory()->create();

    uploadImport($bank, 'questions.pdf', 'not really a pdf')
        ->assertSessionHasErrors('file');
});

it('rejects an oversized file', function () {
    $bank = QuestionBank::factory()->create();

    $big = UploadedFile::fake()->create('questions.csv', 3000); // KB

    test()->withSession(['admin_logged_in' => true])
        ->post(route('admin.store-imported-questions', $bank->id), ['file' => $big])
        ->assertSessionHasErrors('file');
});

it('requires an admin session to import', function () {
    $bank = QuestionBank::factory()->create();

    test()->post(route('admin.store-imported-questions', $bank->id), [])
        ->assertRedirect(route('admin.login'));
});

it('never writes the uploaded file to storage', function () {
    Storage::fake('local');
    $bank = QuestionBank::factory()->create();

    uploadImport($bank, 'questions.csv', csvFile([
        'Kept in memory only,easy,single,A,B,,,,,1',
    ]))->assertSessionHas('success');

    // Spooled imports would pile up in the one persistent volume with nothing
    // to clean them out.
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

/* -------------------------------------------------------------------------- */
/* Pages and templates                                                        */
/* -------------------------------------------------------------------------- */

it('offers an import button on the bank questions page', function () {
    $bank = QuestionBank::factory()->create();

    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.bank-questions', $bank->id))
        ->assertOk()
        ->assertSee(route('admin.import-questions', $bank->id), false);
});

it('shows the import page with both template links', function () {
    $bank = QuestionBank::factory()->create();

    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.import-questions', $bank->id))
        ->assertOk()
        ->assertSee(route('admin.import-template', 'csv'), false)
        ->assertSee(route('admin.import-template', 'json'), false);
});

it('downloads a csv template that the parser accepts', function () {
    $response = test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.import-template', 'csv'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Round-trip: whatever the admin downloads must import cleanly. This is the
    // guarantee that generating the template beats shipping a static file.
    $parsed = app(QuestionImportService::class)->parse($response->getContent(), 'csv');

    expect($parsed['errors']->all())->toBe([]);
    expect($parsed['rows'])->toHaveCount(2);
});

it('downloads a json template', function () {
    $response = test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.import-template', 'json'))
        ->assertOk();

    $parsed = app(QuestionImportService::class)->parse($response->getContent(), 'json');

    expect($parsed['errors']->all())->toBe([]);
});

it('rejects an unknown template format', function () {
    test()->withSession(['admin_logged_in' => true])
        ->get(url('/examadmin/questions/import-template/xlsx'))
        ->assertNotFound();
});
