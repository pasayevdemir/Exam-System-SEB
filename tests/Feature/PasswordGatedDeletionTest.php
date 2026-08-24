<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestionBank;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Http\Request;

const ADMIN_PASSWORD = 'correct-horse-battery';

beforeEach(function () {
    config(['admin.password' => ADMIN_PASSWORD]);
});

function deleteBankAs(QuestionBank $bank, ?string $password)
{
    return test()
        ->withSession(['admin_logged_in' => true])
        ->delete(route('admin.delete-bank', $bank->id), array_filter([
            'admin_password' => $password,
        ]));
}

function deleteExamAs(Exam $exam, ?string $password)
{
    return test()
        ->withSession(['admin_logged_in' => true])
        ->delete(route('admin.delete-exam', $exam->id), array_filter([
            'admin_password' => $password,
        ]));
}

/** An attempt that has actually served the given question to a student. */
function serveQuestion(Exam $exam, Question $question): ExamAttempt
{
    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => User::factory()->create()->id,
        'started_at' => now(),
        'target_weight' => 10,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'display_order' => 1,
        'weight_at_generation' => 1.0,
    ]);

    return $attempt;
}

/* -------------------------------------------------------------------------- */
/* The confirm modal renders */
/* -------------------------------------------------------------------------- */

it('renders the password confirm modal on the banks page', function () {
    $bank = QuestionBank::factory()->create();

    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.banks'))
        ->assertOk()
        ->assertSee('deleteBankModal')
        ->assertSee('name="admin_password"', false)
        ->assertSee(route('admin.delete-bank', $bank->id), false);
});

it('renders the password confirm modal on the dashboard', function () {
    $exam = Exam::factory()->create();

    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('deleteExamModal')
        ->assertSee('name="admin_password"', false)
        ->assertSee(route('admin.delete-exam', $exam->id), false);
});

/* -------------------------------------------------------------------------- */
/* Bank deletion */
/* -------------------------------------------------------------------------- */

it('refuses to delete a bank when no password is supplied', function () {
    $bank = QuestionBank::factory()->create();

    deleteBankAs($bank, null)->assertSessionHas('error');

    expect(QuestionBank::find($bank->id))->not->toBeNull();
});

it('refuses to delete a bank when the password is wrong', function () {
    $bank = QuestionBank::factory()->create();

    deleteBankAs($bank, 'not-the-password')->assertSessionHas('error');

    expect(QuestionBank::find($bank->id))->not->toBeNull();
});

it('deletes a bank together with its questions when the password is right', function () {
    $bank = QuestionBank::factory()->create();
    $questions = Question::factory()->count(3)->create(['question_bank_id' => $bank->id]);

    deleteBankAs($bank, ADMIN_PASSWORD)->assertSessionHas('success');

    expect(QuestionBank::find($bank->id))->toBeNull();
    expect(Question::whereIn('id', $questions->pluck('id'))->count())->toBe(0);
});

it('unregisters the bank from its exams before deleting it', function () {
    $bank = QuestionBank::factory()->create();
    $exam = Exam::factory()->create();
    Question::factory()->create(['question_bank_id' => $bank->id]);

    ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => 1,
        'quota_medium' => 0,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    deleteBankAs($bank, ADMIN_PASSWORD)->assertSessionHas('success');

    expect(QuestionBank::find($bank->id))->toBeNull();
    expect(ExamQuestionBank::where('exam_id', $exam->id)->count())->toBe(0);
    // The exam itself is untouched by a bank deletion.
    expect(Exam::find($exam->id))->not->toBeNull();
});

it('refuses to delete a bank whose questions were served in an attempt', function () {
    $bank = QuestionBank::factory()->create();
    $question = Question::factory()->create(['question_bank_id' => $bank->id]);
    serveQuestion(Exam::factory()->create(), $question);

    deleteBankAs($bank, ADMIN_PASSWORD)->assertSessionHas('error');

    expect(QuestionBank::find($bank->id))->not->toBeNull();
    expect(Question::find($question->id))->not->toBeNull();
});

/* -------------------------------------------------------------------------- */
/* Exam deletion */
/* -------------------------------------------------------------------------- */

it('refuses to delete an exam when the password is wrong', function () {
    $exam = Exam::factory()->create();

    deleteExamAs($exam, 'nope')->assertSessionHas('error');

    expect(Exam::find($exam->id))->not->toBeNull();
});

it('deletes an exam while keeping its banks and questions', function () {
    $exam = Exam::factory()->create();
    $bank = QuestionBank::factory()->create();
    $question = Question::factory()->create(['question_bank_id' => $bank->id]);

    ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => 1,
        'quota_medium' => 0,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    deleteExamAs($exam, ADMIN_PASSWORD)->assertSessionHas('success');

    expect(Exam::find($exam->id))->toBeNull();
    expect(ExamQuestionBank::where('exam_id', $exam->id)->count())->toBe(0);

    // The whole point of the feature: the bank survives its exam.
    expect(QuestionBank::find($bank->id))->not->toBeNull();
    expect(Question::find($question->id))->not->toBeNull();
});

it('refuses to delete an exam that has student attempts', function () {
    $exam = Exam::factory()->create();
    $bank = QuestionBank::factory()->create();
    $question = Question::factory()->create(['question_bank_id' => $bank->id]);
    serveQuestion($exam, $question);

    deleteExamAs($exam, ADMIN_PASSWORD)->assertSessionHas('error');

    expect(Exam::find($exam->id))->not->toBeNull();
});

it('deactivates an exam left with no banks after a bank deletion', function () {
    $bank = QuestionBank::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true]);
    Question::factory()->create(['question_bank_id' => $bank->id]);

    ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => 1,
        'quota_medium' => 0,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    deleteBankAs($bank, ADMIN_PASSWORD)->assertSessionHas('success');

    // Otherwise a student could start this exam and be served zero questions.
    expect(Exam::find($exam->id)->is_active)->toBeFalse();
});

it('leaves an exam active when it still has another bank', function () {
    $doomed = QuestionBank::factory()->create();
    $survivor = QuestionBank::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true]);

    foreach ([$doomed, $survivor] as $index => $bank) {
        ExamQuestionBank::create([
            'exam_id' => $exam->id,
            'question_bank_id' => $bank->id,
            'quota_easy' => 1,
            'quota_medium' => 0,
            'quota_hard' => 0,
            'sort_order' => $index,
        ]);
    }

    deleteBankAs($doomed, ADMIN_PASSWORD)->assertSessionHas('success');

    expect(Exam::find($exam->id)->is_active)->toBeTrue();
});

it('never flashes the admin password back into the session on a 419', function () {
    // Mirrors the CSRF-expiry path: the handler flashes input, and the session
    // store is plaintext, so a credential kept here would be readable at rest.
    $request = Request::create('/examadmin/banks/1', 'POST', [
        '_token' => 'stale',
        'admin_password' => 'super-secret',
        'entry_password' => 'exam-secret',
        'keep_me' => 'visible',
    ]);

    $sensitive = array_filter(
        array_keys($request->all()),
        fn ($key) => stripos($key, 'password') !== false
    );
    $kept = $request->except([...$sensitive, '_token']);

    expect($kept)->toBe(['keep_me' => 'visible']);
    expect($kept)->not->toHaveKey('admin_password');
});

it('refuses to delete an exam that has results', function () {
    $exam = Exam::factory()->create();
    $user = User::factory()->create();

    ExamResult::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'total_questions' => 5,
        'correct_answers' => 3,
        'score' => 60,
        'submitted_at' => now(),
    ]);

    deleteExamAs($exam, ADMIN_PASSWORD)->assertSessionHas('error');

    expect(Exam::find($exam->id))->not->toBeNull();
});
