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
 * submitExam() writes an ExamResult, a StudentAnswer per option, the attempt's
 * completed_at and a draft cleanup. Until these ran in one transaction, a
 * double-click — or the autosave retry logic in exam.blade.php firing twice on a
 * flaky connection — could get two of them past the completed_at check and store
 * two results for one sitting.
 *
 * Scope of these tests: they drive submits *sequentially*, which is what a test
 * process can do. The suite runs on SQLite :memory:, where lockForUpdate() is a
 * no-op, so nothing here proves the row lock itself works under real contention
 * on MySQL. The guarantee that survives on both engines is the unique index on
 * exam_results.exam_attempt_id, asserted at the bottom of this file — treat that
 * as the real backstop and these as the guard against the guard being removed.
 */

use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Asserting on the transaction directly rather than on its effects: a partial
// write needs the request to die halfway, which a feature test cannot stage
// without mocking the writes it is supposed to be checking. The transaction
// level at insert time is the same fact, observable and engine-independent.
it('writes the result inside a transaction', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    // RefreshDatabase already holds a transaction open around every test, so the
    // baseline is 1, not 0 — the assertion has to be "deeper than the harness",
    // otherwise it passes whether or not submitExam opens one of its own.
    $baseline = DB::transactionLevel();
    $levelAtResult = null;
    $levelAtAnswer = null;

    ExamResult::created(function () use (&$levelAtResult) {
        $levelAtResult = DB::transactionLevel();
    });
    StudentAnswer::created(function () use (&$levelAtAnswer) {
        $levelAtAnswer = DB::transactionLevel();
    });

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    expect($levelAtResult)->toBeGreaterThan($baseline)
        ->and($levelAtAnswer)->toBeGreaterThan($baseline);
});

it('stores exactly one result when the same exam is submitted twice', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);
    $questionId = $seed['questions'][0]['question']->id;

    submitAnswers($user, $seed['exam'], ['answers' => [$questionId => 2]]);
    $second = submitAnswers($user, $seed['exam'], ['answers' => [$questionId => 2]]);

    expect(ExamResult::count())->toBe(1);
    $second->assertRedirect(route('student.exams'));
    $second->assertSessionHas('error', 'You have already completed this exam.');
});

it('does not add answer rows on the second submit', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'multiple', 'options' => 4, 'correct' => [0, 2]],
    ]);
    $questionId = $seed['questions'][0]['question']->id;

    submitAnswers($user, $seed['exam'], ['answers' => [$questionId => [0, 2]]]);
    $afterFirst = StudentAnswer::count();

    submitAnswers($user, $seed['exam'], ['answers' => [$questionId => [0, 1]]]);

    expect(StudentAnswer::count())->toBe($afterFirst);
});

it('leaves the first submit intact and the attempt closed', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);
    $questionId = $seed['questions'][0]['question']->id;

    submitAnswers($user, $seed['exam'], ['answers' => [$questionId => 2]]);
    submitAnswers($user, $seed['exam'], ['answers' => [$questionId => 0]]);

    // The wrong answer from the replay must not overwrite the scored one.
    expect(ExamResult::first()->score)->toBe(1)
        ->and(ExamAttempt::find($seed['attempt']->id)->completed_at)->not->toBeNull();
});

// The application-level guard is the lock; this is the guard underneath it, and
// unlike the lock it holds on SQLite and MySQL alike.
it('refuses a second result row for the same attempt at the database level', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    expect(fn () => ExamResult::create([
        'exam_id' => $seed['exam']->id,
        'exam_attempt_id' => $seed['attempt']->id,
        'user_id' => $user->id,
        'total_questions' => 1,
        'correct_answers' => 0,
        'score' => 0,
        'submitted_at' => now(),
    ]))->toThrow(QueryException::class);
});

// Results predating the attempt system carry a null exam_attempt_id; a unique
// index over a nullable column must not collapse them into one row.
it('still allows several results with no attempt attached', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    foreach (range(1, 2) as $i) {
        ExamResult::create([
            'exam_id' => $seed['exam']->id,
            'exam_attempt_id' => null,
            'user_id' => $user->id,
            'total_questions' => 1,
            'correct_answers' => 0,
            'score' => 0,
            'submitted_at' => now(),
        ]);
    }

    expect(ExamResult::whereNull('exam_attempt_id')->count())->toBe(2);
});
