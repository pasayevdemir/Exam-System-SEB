<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

/**
 * The results export, read back cell by cell.
 *
 * This is the artifact an examiner opens when a mark is disputed, and until now
 * two hundred and sixty lines of it were built inside a controller method with
 * no test at all - the only way to check a column was to download the file and
 * look.
 */

use App\Exports\ExamResultsWorkbook;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function buildWorkbook(Exam $exam): Spreadsheet
{
    $results = ExamResult::where('exam_id', $exam->id)
        ->with('user', 'studentAnswers.question.answers', 'studentAnswers.answer', 'examAttempt.attemptQuestions')
        ->orderBy('submitted_at', 'desc')
        ->get();

    return (new ExamResultsWorkbook($exam, $results))->build();
}

/**
 * One student, one submitted result, no answers on it yet.
 *
 * @return array{exam: Exam, user: User, result: ExamResult, bank: QuestionBank}
 */
function seedSubmission(array $resultAttributes = []): array
{
    $exam = Exam::factory()->create(['exam_id' => 'ALG-1', 'exam_name' => 'Algebra']);
    $user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'fin_code' => 'FIN123']);

    $result = ExamResult::create(array_merge([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'total_questions' => 2,
        'correct_answers' => 1,
        'score' => 1,
        'submitted_at' => now(),
    ], $resultAttributes));

    return ['exam' => $exam, 'user' => $user, 'result' => $result, 'bank' => QuestionBank::factory()->create()];
}

/**
 * An MCQ question whose first option is the correct one, answered with the
 * option at $chosen.
 */
function answerMcq(array $seed, int $chosen, string $correctText = 'Four'): StudentAnswer
{
    $question = Question::factory()->create([
        'question_bank_id' => $seed['bank']->id,
        'question_type' => 'single',
        'question_text' => 'What is 2 + 2?',
    ]);

    $options = collect([$correctText, 'Five'])->map(fn ($text, $i) => Answer::create([
        'question_id' => $question->id,
        'answer_text' => $text,
        'is_correct' => $i === 0,
    ]));

    return StudentAnswer::create([
        'exam_result_id' => $seed['result']->id,
        'question_id' => $question->id,
        'answer_id' => $options[$chosen]->id,
    ]);
}

function answerWithFile(array $seed, array $attributes = []): StudentAnswer
{
    $question = Question::factory()->create([
        'question_bank_id' => $seed['bank']->id,
        'question_type' => 'file_upload',
        'question_text' => 'Upload your proof.',
        'file_upload_settings' => ['allowed_extensions' => ['pdf'], 'max_size_mb' => 10],
    ]);

    return StudentAnswer::create(array_merge([
        'exam_result_id' => $seed['result']->id,
        'question_id' => $question->id,
        'file_path' => 'exam_submissions/proof.pdf',
        'file_size' => 2048,
        'is_graded' => false,
    ], $attributes));
}

describe('the summary sheet', function () {
    it('opens with the exam it belongs to', function () {
        $seed = seedSubmission();
        $sheet = buildWorkbook($seed['exam'])->getSheet(0);

        expect($sheet->getTitle())->toBe('Exam Results')
            ->and($sheet->getCell('B1')->getValue())->toBe('Algebra')
            ->and($sheet->getCell('B2')->getValue())->toBe('ALG-1')
            ->and($sheet->getCell('B3')->getValue())->toBe(1);
    });

    it('heads the table with the eight result columns', function () {
        $seed = seedSubmission();
        $sheet = buildWorkbook($seed['exam'])->getSheet(0);

        expect(collect(range('A', 'H'))->map(fn ($c) => $sheet->getCell($c.'7')->getValue())->all())
            ->toBe(['Full Name', 'FIN Code', 'Score', 'Max Score', 'Correct Answers', 'Total Questions',
                'Percentage', 'Submitted At']);
    });

    it('writes a row per student under the header', function () {
        $seed = seedSubmission();
        $sheet = buildWorkbook($seed['exam'])->getSheet(0);

        expect($sheet->getCell('A8')->getValue())->toBe('Ada Lovelace')
            ->and($sheet->getCell('B8')->getValue())->toBe('FIN123')
            ->and($sheet->getCell('C8')->getValue())->toBe(1.0)
            ->and($sheet->getCell('D8')->getValue())->toBe(2.0)
            ->and($sheet->getCell('G8')->getValue())->toBe('50%');
    });

    // Dividing by a total of zero rather than reporting one.
    it('reports no percentage rather than failing on an empty paper', function () {
        $seed = seedSubmission(['total_questions' => 0, 'correct_answers' => 0, 'score' => 0]);
        $sheet = buildWorkbook($seed['exam'])->getSheet(0);

        expect($sheet->getCell('G8')->getValue())->toBe('0%');
    });
});

describe('the detailed answers sheet', function () {
    it('heads the table with the thirteen answer columns', function () {
        $seed = seedSubmission();
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getTitle())->toBe('Detailed Answers')
            ->and(collect(range('A', 'M'))->map(fn ($c) => $sheet->getCell($c.'1')->getValue())->all())
            ->toBe(['Full Name', 'FIN Code', 'Question #', 'Question Text', 'Question Type', 'Student Answer',
                'Correct Answer', 'Is Correct', 'File Submission', 'File Size', 'Grading Status', 'Graded Score',
                'Grading Notes']);
    });

    it('puts the chosen option beside the correct one', function () {
        $seed = seedSubmission();
        answerMcq($seed, chosen: 1);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('D2')->getValue())->toBe('What is 2 + 2?')
            ->and($sheet->getCell('E2')->getValue())->toBe('Multiple Choice')
            ->and($sheet->getCell('F2')->getValue())->toBe('Five')
            ->and($sheet->getCell('G2')->getValue())->toBe('Four');
    });

    // student_answers.is_correct has never existed as a column, so it read null
    // on every row: the verdict cell said "Correct" while the fill behind it was
    // the red one and the score column said 0.
    it('agrees with itself about a correct answer', function () {
        $seed = seedSubmission();
        answerMcq($seed, chosen: 0);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('H2')->getValue())->toBe('Correct')
            ->and($sheet->getCell('L2')->getValue())->toBe('1')
            ->and($sheet->getStyle('H2')->getFill()->getStartColor()->getRGB())->toBe('C6EFCE');
    });

    it('agrees with itself about a wrong answer', function () {
        $seed = seedSubmission();
        answerMcq($seed, chosen: 1);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('H2')->getValue())->toBe('Incorrect')
            ->and($sheet->getCell('L2')->getValue())->toBe('0')
            ->and($sheet->getStyle('H2')->getFill()->getStartColor()->getRGB())->toBe('FFC7CE');
    });
});

describe('a file upload answer', function () {
    it('links the submission through the authenticated download', function () {
        $seed = seedSubmission();
        $submission = answerWithFile($seed);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        // Not a /storage/ URL: uploads live on the private disk, so a public
        // path for one has nothing behind it.
        expect($sheet->getCell('I2')->getValue())->toBe(route('admin.download-submission', $submission->id))
            ->and($sheet->getCell('I2')->getValue())->not->toContain('/storage/')
            ->and($sheet->getCell('J2')->getValue())->toBe('2 KB');
    });

    it('stays amber while it is still waiting to be marked', function () {
        $seed = seedSubmission();
        answerWithFile($seed);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('H2')->getValue())->toBe('Pending')
            ->and($sheet->getCell('K2')->getValue())->toBe('Pending')
            ->and($sheet->getStyle('H2')->getFill()->getStartColor()->getRGB())->toBe('FFEB9C');
    });

    it('turns green once it is marked at or above the pass mark', function () {
        $seed = seedSubmission();
        answerWithFile($seed, ['is_graded' => true, 'manual_score' => 50, 'admin_feedback' => 'Clear working.']);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('H2')->getValue())->toBe('Passed')
            ->and($sheet->getCell('K2')->getValue())->toBe('Graded')
            ->and($sheet->getCell('M2')->getValue())->toBe('Clear working.')
            ->and($sheet->getStyle('H2')->getFill()->getStartColor()->getRGB())->toBe('C6EFCE');
    });

    it('turns red once it is marked below the pass mark', function () {
        $seed = seedSubmission();
        answerWithFile($seed, ['is_graded' => true, 'manual_score' => 49]);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('H2')->getValue())->toBe('Failed')
            ->and($sheet->getStyle('H2')->getFill()->getStartColor()->getRGB())->toBe('FFC7CE');
    });

    it('says so plainly when no file arrived', function () {
        $seed = seedSubmission();
        answerWithFile($seed, ['file_path' => null, 'file_size' => null]);
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('I2')->getValue())->toBe('No file submitted')
            ->and($sheet->getCell('J2')->getValue())->toBe('');
    });
});

// Answer text is written by an admin and read back by a spreadsheet, so it is
// the classic formula-injection route into a machine that is not this one.
describe('cells that could be read as formulas', function () {
    it('neutralises an answer that starts like one', function () {
        $seed = seedSubmission();
        answerMcq($seed, chosen: 0, correctText: '=cmd|\'/c calc\'!A1');
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('F2')->getValue())->toStartWith("'=")
            ->and($sheet->getCell('F2')->getDataType())->toBe('s');
    });

    it('flattens a line break that would split the row on re-export', function () {
        $seed = seedSubmission();
        answerMcq($seed, chosen: 0, correctText: "first\nsecond");
        $sheet = buildWorkbook($seed['exam'])->getSheet(1);

        expect($sheet->getCell('F2')->getValue())->toBe('first second');
    });
});

describe('the download endpoint', function () {
    it('streams the workbook as an attachment', function () {
        $seed = seedSubmission();
        answerMcq($seed, chosen: 0);

        $response = test()->withSession(['admin_logged_in' => true])
            ->get(route('admin.download-results', $seed['exam']->id));

        $response->assertOk();

        expect($response->headers->get('content-disposition'))->toContain('attachment')
            ->and($response->headers->get('content-disposition'))->toContain('exam_results_ALG-1')
            ->and($response->streamedContent())->toStartWith('PK');  // a zip, which is what xlsx is
    });

    it('sends the admin back with a message when there is nothing to export', function () {
        $exam = Exam::factory()->create();

        test()->withSession(['admin_logged_in' => true])
            ->get(route('admin.download-results', $exam->id))
            ->assertRedirect()
            ->assertSessionHas('error', 'No results found for this exam.');
    });

    it('keeps the export behind admin auth', function () {
        $exam = Exam::factory()->create();

        test()->get(route('admin.download-results', $exam->id))
            ->assertRedirect(route('admin.login'));
    });
});

// exam_id is free text an admin types, and "2024/Fall" is an ordinary thing to
// call an exam. Symfony refuses a Content-Disposition filename containing a
// path separator, so it reached the browser as a 500 rather than a download.
it('survives an exam id that is not filename-safe', function () {
    $exam = Exam::factory()->create(['exam_id' => '2024/Fall']);
    ExamResult::create([
        'exam_id' => $exam->id,
        'user_id' => User::factory()->create()->id,
        'total_questions' => 1,
        'correct_answers' => 1,
        'score' => 1,
        'submitted_at' => now(),
    ]);

    $response = test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.download-results', $exam->id));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('2024-Fall');
});

// One multiple-choice question answered with two options is two rows on this
// sheet. Numbering them by row made the second option look like question 2, and
// pushed every question after it off by one.
it('numbers the rows by question rather than by row', function () {
    $seed = seedSubmission();
    $first = answerMcq($seed, chosen: 0);

    // A second option chosen on that same question.
    StudentAnswer::create([
        'exam_result_id' => $seed['result']->id,
        'question_id' => $first->question_id,
        'answer_id' => Answer::where('question_id', $first->question_id)->orderByDesc('id')->value('id'),
    ]);

    answerMcq($seed, chosen: 0);

    $sheet = buildWorkbook($seed['exam'])->getSheet(1);

    expect([
        $sheet->getCell('C2')->getValue(),
        $sheet->getCell('C3')->getValue(),
        $sheet->getCell('C4')->getValue(),
    ])->toBe([1, 1, 2]);
});
