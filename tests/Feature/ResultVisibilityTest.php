<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
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
 * A finished attempt with one graded answer, which is what the per-question
 * breakdown on the results page is rendered from.
 *
 * @return array{user: User, exam: Exam, attempt: ExamAttempt, result: ExamResult, correct: Answer}
 */
function seedFinishedAttempt(): array
{
    $user = User::factory()->create();
    $exam = Exam::factory()->create();

    $question = Question::factory()->create([
        'question_bank_id' => QuestionBank::factory(),
        'question_type' => 'single',
    ]);

    $correct = Answer::create([
        'question_id' => $question->id,
        'answer_text' => 'The correct one',
        'is_correct' => true,
    ]);
    Answer::create([
        'question_id' => $question->id,
        'answer_text' => 'A distractor',
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

    return compact('user', 'exam', 'attempt', 'result', 'correct');
}

it('shows the per-question breakdown when no retake is open', function () {
    $seed = seedFinishedAttempt();

    test()->actingAs($seed['user'])
        ->get(route('student.show-result', $seed['result']->id))
        ->assertOk()
        ->assertSee('Question by Question Results')
        ->assertSee($seed['correct']->answer_text);
});

it('hides the answer key while a retake is in progress', function () {
    $seed = seedFinishedAttempt();

    // Admin grants a retake, then the student starts the new attempt.
    $seed['attempt']->update(['superseded_at' => now()]);
    ExamAttempt::create([
        'exam_id' => $seed['exam']->id,
        'user_id' => $seed['user']->id,
        'started_at' => now(),
    ]);

    $response = test()->actingAs($seed['user'])
        ->get(route('student.show-result', $seed['result']->id))
        ->assertOk();

    $response->assertDontSee('Question by Question Results')
        ->assertDontSee($seed['correct']->answer_text)
        ->assertSee('Answer details are hidden');
});

it('still shows the score while the answer key is hidden', function () {
    $seed = seedFinishedAttempt();

    $seed['attempt']->update(['superseded_at' => now()]);
    ExamAttempt::create([
        'exam_id' => $seed['exam']->id,
        'user_id' => $seed['user']->id,
        'started_at' => now(),
    ]);

    test()->actingAs($seed['user'])
        ->get(route('student.show-result', $seed['result']->id))
        ->assertOk()
        ->assertSee('Final Score');
});

it('shows the breakdown again once the retake is submitted', function () {
    $seed = seedFinishedAttempt();

    $seed['attempt']->update(['superseded_at' => now()]);
    ExamAttempt::create([
        'exam_id' => $seed['exam']->id,
        'user_id' => $seed['user']->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    test()->actingAs($seed['user'])
        ->get(route('student.show-result', $seed['result']->id))
        ->assertOk()
        ->assertSee('Question by Question Results');
});

it('keeps another student out of a result that is not theirs', function () {
    $seed = seedFinishedAttempt();
    $intruder = User::factory()->create();

    test()->actingAs($intruder)
        ->get(route('student.show-result', $seed['result']->id))
        ->assertForbidden();
});
