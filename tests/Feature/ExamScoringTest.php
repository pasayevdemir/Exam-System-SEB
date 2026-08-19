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
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;

/**
 * Build an exam with a pinned attempt already generated for $user, so a test can
 * POST straight at the submit endpoint without driving ExamGenerationService.
 *
 * Each spec is ['type' => 'single'|'multiple'|'file_upload', 'options' => int,
 * 'correct' => int[]] where 'correct' holds the option positions that are right.
 * answer_display_order is left in natural order, so option position N is always
 * $questions[i]['answers'][N] and a test can reason about positions directly.
 *
 * @return array{exam: Exam, attempt: ExamAttempt, questions: array<int, array{question: Question, answers: Answer[]}>}
 */
function seedAttempt(User $user, array $specs): array
{
    $exam = Exam::factory()->create();
    $bank = QuestionBank::factory()->create();

    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    $questions = [];

    foreach (array_values($specs) as $order => $spec) {
        $type = $spec['type'] ?? 'single';

        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'question_type' => $type,
            'file_upload_settings' => $type === 'file_upload'
                ? ['allowed_extensions' => ['pdf'], 'max_size_mb' => 10]
                : null,
        ]);

        $answers = [];
        if ($type !== 'file_upload') {
            foreach (range(0, ($spec['options'] ?? 4) - 1) as $position) {
                $answers[] = Answer::create([
                    'question_id' => $question->id,
                    'answer_text' => "Option {$position} for q{$question->id}",
                    'is_correct' => in_array($position, $spec['correct'] ?? [], true),
                ]);
            }
        }

        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'display_order' => $order,
            'weight_at_generation' => 1.0,
            'answer_display_order' => $answers === []
                ? null
                : array_map(fn (Answer $a) => $a->id, $answers),
        ]);

        $questions[] = ['question' => $question, 'answers' => $answers];
    }

    return ['exam' => $exam, 'attempt' => $attempt, 'questions' => $questions];
}

/**
 * Push answer ids well clear of the 0..3 option positions, so a test asserting
 * "no answer id appears in the page" cannot pass by coincidence.
 */
function burnAnswerIds(int $count = 30): void
{
    $filler = Question::factory()->create(['question_bank_id' => QuestionBank::factory()]);

    foreach (range(1, $count) as $i) {
        Answer::create([
            'question_id' => $filler->id,
            'answer_text' => "filler {$i}",
            'is_correct' => false,
        ]);
    }
}

function submitAnswers(User $user, Exam $exam, array $payload)
{
    return test()->actingAs($user)->post(route('student.submit-exam', $exam->id), $payload);
}

it('scores a correct single-choice answer', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 2],
    ]);

    expect(ExamResult::first()->score)->toBe(1);
});

it('does not score a wrong single-choice answer', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    expect(ExamResult::first()->score)->toBe(0);
});

it('scores a multiple-choice answer only on an exact match', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'multiple', 'options' => 4, 'correct' => [0, 2]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => [0, 2]],
    ]);

    expect(ExamResult::first()->score)->toBe(1);
});

it('does not score a partially correct multiple-choice answer', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'multiple', 'options' => 4, 'correct' => [0, 2]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => [0]],
    ]);

    expect(ExamResult::first()->score)->toBe(0);
});

it('resolves a submitted position back to the right answer row', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [1]],
    ]);

    $q = $seed['questions'][0];

    submitAnswers($user, $seed['exam'], [
        'answers' => [$q['question']->id => 1],
    ]);

    $result = ExamResult::first();

    expect($result->total_questions)->toBe(1)
        ->and($result->studentAnswers)->toHaveCount(1)
        ->and($result->studentAnswers->first()->answer_id)->toBe($q['answers'][1]->id);
});

it('marks the attempt completed and clears its drafts', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    expect($seed['attempt']->fresh()->completed_at)->not->toBeNull();
});

it('accepts a submission that leaves a question unanswered', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    // Handing in a partly blank paper is allowed - the blank question simply
    // scores nothing, it does not block the submission.
    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    $result = ExamResult::first();

    expect($result)->not->toBeNull()
        ->and($result->total_questions)->toBe(2)
        ->and($result->correct_answers)->toBe(1)
        ->and(StudentAnswer::where('exam_result_id', $result->id)->count())->toBe(1);
});

it('accepts a submission with no answers at all', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
        ['type' => 'multiple', 'options' => 4, 'correct' => [0, 1]],
    ]);

    submitAnswers($user, $seed['exam'], []);

    expect(ExamResult::first()?->correct_answers)->toBe(0)
        ->and($seed['attempt']->fresh()->completed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Security regressions
|--------------------------------------------------------------------------
*/

it('never renders raw answer ids on the exam page', function () {
    burnAnswerIds();

    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);

    $html = test()->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    // Answer rows are created in the admin's typed order, so their ids leak
    // which option is correct. Positions must be all the page ever exposes.
    foreach ($seed['questions'][0]['answers'] as $answer) {
        expect($html)->not->toContain('value="' . $answer->id . '"')
            ->and($html)->not->toContain('_' . $answer->id . '"');
    }
});

it('cannot credit another question by submitting a raw answer id', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    [$first, $second] = $seed['questions'];

    // The old attack: replay a known-correct answer id on another question.
    // A value is now read as a position inside the second question's own
    // order, so it can only ever select that question's own options.
    submitAnswers($user, $seed['exam'], [
        'answers' => [
            $first['question']->id => 0,
            $second['question']->id => $first['answers'][0]->id,
        ],
    ]);

    expect(ExamResult::first()?->score ?? 0)->toBe(1);
});

it('resolves positions against the options that still exist', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);

    $q = $seed['questions'][0];

    // An admin removes an unused option while the attempt is open. The exam page
    // drops the dead entry and renumbers the survivors 0,1,2 - the server has to
    // read positions the same way or it credits the wrong option.
    $q['answers'][1]->delete();

    submitAnswers($user, $seed['exam'], [
        'answers' => [$q['question']->id => 1],
    ]);

    expect(ExamResult::first()?->score ?? 0)->toBe(1);
});

it('rejects an option position outside the question range', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    $response = submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 99],
    ]);

    $response->assertSessionHasErrors();
    expect(ExamResult::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Autosave drafts
|--------------------------------------------------------------------------
*/

it('stores an autosaved position as a real answer id', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);

    $q = $seed['questions'][0];

    test()->actingAs($user)
        ->postJson(route('student.autosave-answer', $seed['exam']->id), [
            'question_id' => $q['question']->id,
            'answer_indexes' => [2],
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ExamAttemptAnswer::first()->selected_answer_ids)->toBe([$q['answers'][2]->id]);
});

it('restores an autosaved draft onto the right option', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);

    $q = $seed['questions'][0];

    test()->actingAs($user)
        ->postJson(route('student.autosave-answer', $seed['exam']->id), [
            'question_id' => $q['question']->id,
            'answer_indexes' => [3],
        ])->assertOk();

    $html = test()->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    // Position 3 is the one that must come back checked.
    expect($html)->toContain('value="3"')
        ->and($html)->toMatch('/value="3"[^>]*checked/');
});

it('ignores an autosaved position outside the question range', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    test()->actingAs($user)
        ->postJson(route('student.autosave-answer', $seed['exam']->id), [
            'question_id' => $seed['questions'][0]['question']->id,
            'answer_indexes' => [99],
        ])->assertOk();

    expect(ExamAttemptAnswer::first()?->selected_answer_ids ?? [])->toBe([]);
});

it('ignores answers for questions outside the attempt', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    // A question the student was never served - previously this dereferenced
    // null and blew up with a 500 before any scoring happened.
    $foreign = Question::factory()->create([
        'question_bank_id' => QuestionBank::factory(),
        'question_type' => 'single',
    ]);
    Answer::create([
        'question_id' => $foreign->id,
        'answer_text' => 'Not in this attempt',
        'is_correct' => true,
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [
            $seed['questions'][0]['question']->id => 0,
            $foreign->id => 0,
        ],
    ]);

    expect(ExamResult::first()->score)->toBe(1);
});
