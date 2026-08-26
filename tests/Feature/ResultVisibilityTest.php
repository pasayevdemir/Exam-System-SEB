<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;

/**
 * A finished attempt with one graded answer.
 *
 * The question and answer texts are given distinctive literals so the tests can
 * assert they never reach the page — that is the whole point of this file.
 *
 * @return array{user: User, exam: Exam, attempt: ExamAttempt, result: ExamResult, question: Question, correct: Answer, distractor: Answer}
 */
function seedFinishedAttempt(): array
{
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['exam_name' => 'Algorithms Midterm', 'time_limit_minutes' => 45]);

    $question = Question::factory()->create([
        'question_bank_id' => QuestionBank::factory(),
        'question_type' => 'single',
        'question_text' => 'WHAT-IS-THE-CAPITAL-OF-FRANCE',
    ]);

    $correct = Answer::create([
        'question_id' => $question->id,
        'answer_text' => 'THE-CORRECT-ONE',
        'is_correct' => true,
    ]);
    $distractor = Answer::create([
        'question_id' => $question->id,
        'answer_text' => 'A-DISTRACTOR',
        'is_correct' => false,
    ]);

    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now()->subHour(),
        'completed_at' => now()->subMinutes(30),
    ]);

    $result = ExamResult::create([
        'exam_id' => $exam->id,
        'exam_attempt_id' => $attempt->id,
        'user_id' => $user->id,
        'total_questions' => 1,
        'correct_answers' => 1,
        'score' => 1,
        'submitted_at' => now()->subMinutes(30),
    ]);

    StudentAnswer::create([
        'exam_result_id' => $result->id,
        'question_id' => $question->id,
        'answer_id' => $correct->id,
    ]);

    return compact('user', 'exam', 'attempt', 'result', 'question', 'correct', 'distractor');
}

function viewResult(array $seed)
{
    return test()->actingAs($seed['user'])
        ->get(route('student.show-result', $seed['result']->id));
}

/* -------------------------------------------------------------------------- */
/* The page must never expose exam content */
/* -------------------------------------------------------------------------- */

it('never shows the question text on a student result', function () {
    $seed = seedFinishedAttempt();

    viewResult($seed)->assertOk()->assertDontSee($seed['question']->question_text);
});

it('never shows the answer key on a student result', function () {
    $seed = seedFinishedAttempt();

    viewResult($seed)->assertOk()
        ->assertDontSee($seed['correct']->answer_text)
        ->assertDontSee($seed['distractor']->answer_text);
});

it('never shows a per question breakdown', function () {
    $seed = seedFinishedAttempt();

    viewResult($seed)->assertOk()
        ->assertDontSee('Question by Question Results')
        ->assertDontSee('Answer Options');
});

it('tells the student that a per question review is unavailable', function () {
    $seed = seedFinishedAttempt();

    viewResult($seed)->assertOk()->assertSee('Sual-sual baxış mövcud deyil');
});

it('hides exam content even while a retake is in progress', function () {
    // The page used to gate itself on an open retake purely because it printed
    // the answer key. It no longer prints one, so this must hold either way.
    $seed = seedFinishedAttempt();

    $seed['attempt']->update(['superseded_at' => now()]);
    ExamAttempt::create([
        'exam_id' => $seed['exam']->id,
        'user_id' => $seed['user']->id,
        'started_at' => now(),
    ]);

    viewResult($seed)->assertOk()
        ->assertDontSee($seed['question']->question_text)
        ->assertDontSee($seed['correct']->answer_text);
});

it('still shows the score while a retake is in progress', function () {
    $seed = seedFinishedAttempt();

    $seed['attempt']->update(['superseded_at' => now()]);
    ExamAttempt::create([
        'exam_id' => $seed['exam']->id,
        'user_id' => $seed['user']->id,
        'started_at' => now(),
    ]);

    viewResult($seed)->assertOk()->assertSee('Yekun bal');
});

/* -------------------------------------------------------------------------- */
/* What the student is still shown */
/* -------------------------------------------------------------------------- */

it('shows the sitting summary', function () {
    $seed = seedFinishedAttempt();

    viewResult($seed)->assertOk()
        ->assertSee('Algorithms Midterm')
        ->assertSee('Yekun bal')
        ->assertSee($seed['user']->fin_code)
        ->assertSee('İmtahan təfərrüatları');
});

it('shows how long the sitting took and how long was allowed', function () {
    $seed = seedFinishedAttempt();

    viewResult($seed)->assertOk()
        ->assertSee('30 dəq')  // started an hour ago, submitted 30 minutes ago
        ->assertSee('45 dəq'); // the exam's time limit
});

it('rounds a part-minute sitting to a whole number', function () {
    // Carbon returns a float, which would otherwise render as "30.41666 min".
    $seed = seedFinishedAttempt();
    $seed['attempt']->update([
        'started_at' => now()->subMinutes(30)->subSeconds(25),
        'completed_at' => now(),
    ]);

    viewResult($seed)->assertOk()
        ->assertSee('30 dəq')
        ->assertDontSee('30.4');
});

it('copes with a result whose attempt has been cleared', function () {
    // exam_attempt_id is SET NULL when history is wiped, so a result can outlive
    // its attempt and the page must not blow up on the missing duration.
    $seed = seedFinishedAttempt();
    $seed['result']->update(['exam_attempt_id' => null]);

    viewResult($seed)->assertOk()->assertSee('Algorithms Midterm');
});

it('reports grading as pending while a file upload is ungraded', function () {
    $seed = seedFinishedAttempt();

    StudentAnswer::create([
        'exam_result_id' => $seed['result']->id,
        'question_id' => $seed['question']->id,
        'file_path' => 'exam_submissions/whatever.pdf',
        'is_graded' => false,
    ]);

    viewResult($seed)->assertOk()
        ->assertSee('qiymətləndirilir')
        ->assertDontSee('Final Score');
});

/* -------------------------------------------------------------------------- */
/* Access control */
/* -------------------------------------------------------------------------- */

it('keeps another student out of a result that is not theirs', function () {
    $seed = seedFinishedAttempt();
    $intruder = User::factory()->create();

    test()->actingAs($intruder)
        ->get(route('student.show-result', $seed['result']->id))
        ->assertForbidden();
});

it('keeps a guest out of a result page', function () {
    $seed = seedFinishedAttempt();

    test()->get(route('student.show-result', $seed['result']->id))
        ->assertRedirect(route('student.login'));
});
