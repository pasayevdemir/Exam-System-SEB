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
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;

it('gives the student a way back to the app after finishing an exam', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->create();
    $bank = QuestionBank::factory()->create();

    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    $question = Question::factory()->create([
        'question_bank_id' => $bank->id,
        'question_type' => 'single',
    ]);

    $correct = Answer::create([
        'question_id' => $question->id,
        'answer_text' => 'Right',
        'is_correct' => true,
    ]);
    $wrong = Answer::create([
        'question_id' => $question->id,
        'answer_text' => 'Wrong',
        'is_correct' => false,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'display_order' => 0,
        'weight_at_generation' => 1.0,
        'answer_display_order' => [$correct->id, $wrong->id],
    ]);

    $response = test()->actingAs($user)
        ->post(route('student.submit-exam', $exam->id), [
            'answers' => [$question->id => 0],
        ]);

    $response->assertOk()
        ->assertSee(route('student.logout'))
        ->assertSee(route('student.exams'))
        ->assertSee('Take Another Exam')
        ->assertSee('automatically logged out');
});
