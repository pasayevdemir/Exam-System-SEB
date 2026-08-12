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
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;

beforeEach(function () {
    // The map is a rendering concern; SEB enforcement is covered by its own suite.
    config(['seb.required' => false]);
});

/**
 * An active exam with a pinned attempt whose questions come from two named
 * banks, deliberately interleaved rather than in contiguous blocks. The
 * generator now emits blocks, but attempts pinned before that change are still
 * interleaved on disk, so the sidebar has to regroup from the questions rather
 * than trust the page order.
 *
 * @return array{exam: Exam, attempt: ExamAttempt, banks: array<string, QuestionBank>, questions: array<int, Question>}
 */
function seedTwoBankAttempt(User $user): array
{
    $exam = Exam::factory()->create(['is_active' => true]);
    $english = QuestionBank::factory()->create(['name' => 'English Language']);
    $logic = QuestionBank::factory()->create(['name' => 'Logical Reasoning']);

    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    $questions = [];

    // Alternating banks so display_order 0,2 are English and 1,3 are Logic.
    foreach ([$english, $logic, $english, $logic] as $order => $bank) {
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'question_type' => 'single',
        ]);

        $answers = [];
        foreach (range(0, 3) as $position) {
            $answers[] = Answer::create([
                'question_id' => $question->id,
                'answer_text' => "Option {$position} for q{$question->id}",
                'is_correct' => $position === 0,
            ]);
        }

        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'display_order' => $order,
            'weight_at_generation' => 1.0,
            'answer_display_order' => array_map(fn (Answer $a) => $a->id, $answers),
        ]);

        $questions[] = $question;
    }

    return [
        'exam' => $exam,
        'attempt' => $attempt,
        'banks' => ['english' => $english, 'logic' => $logic],
        'questions' => $questions,
    ];
}

it('renders a question map grouped by bank', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankAttempt($user);

    $this->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->assertSee('Question Map')
        ->assertSee('English Language')
        ->assertSee('Logical Reasoning');
});

it('gives every question a map button and a scroll anchor', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankAttempt($user);

    $response = $this->actingAs($user)->get(route('student.exam', $seed['exam']->id))->assertOk();

    foreach ($seed['questions'] as $question) {
        $response->assertSee('id="question-card-' . $question->id . '"', false);
        $response->assertSee('data-question="' . $question->id . '"', false);
    }
});

it('tags each question card with its bank name', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankAttempt($user);

    $response = $this->actingAs($user)->get(route('student.exam', $seed['exam']->id))->assertOk();

    // Per bank: two card tags, one sidebar group heading, two map button titles.
    expect(substr_count($response->getContent(), 'English Language'))->toBe(5)
        ->and(substr_count($response->getContent(), 'Logical Reasoning'))->toBe(5);
});

it('gives each bank its own tag colour', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankAttempt($user);

    $response = $this->actingAs($user)->get(route('student.exam', $seed['exam']->id))->assertOk();

    $response->assertSee('ps-bank-1', false)->assertSee('ps-bank-2', false);
});

it('still exposes no answer ids in the page', function () {
    $user = User::factory()->create();

    // Push real answer ids well past the 0..3 option positions first, so
    // value="1" cannot match an answer id by coincidence and pass the check.
    $filler = Question::factory()->create(['question_bank_id' => QuestionBank::factory()]);
    foreach (range(1, 30) as $i) {
        Answer::create(['question_id' => $filler->id, 'answer_text' => "filler {$i}", 'is_correct' => false]);
    }

    $seed = seedTwoBankAttempt($user);

    // The map added new data-* attributes near the options; make sure none of
    // them leaked an answer id, which would hand over the correct option.
    $content = $this->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    foreach (Answer::where('is_correct', true)->pluck('id') as $answerId) {
        expect($content)->not->toContain('value="' . $answerId . '"');
    }
});

it('marks a restored draft answer so the map can render it green', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankAttempt($user);

    $attemptQuestion = ExamAttemptQuestion::where('exam_attempt_id', $seed['attempt']->id)
        ->where('question_id', $seed['questions'][0]->id)
        ->first();

    ExamAttemptAnswer::create([
        'exam_attempt_id' => $seed['attempt']->id,
        'exam_attempt_question_id' => $attemptQuestion->id,
        'selected_answer_ids' => [$attemptQuestion->answer_display_order[1]],
    ]);

    // updateProgress() derives the green state from checked inputs, so the
    // restored draft has to arrive already checked in the markup.
    $this->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->assertSee('id="answer_' . $seed['questions'][0]->id . '_1"', false)
        ->assertSee('checked', false);
});
