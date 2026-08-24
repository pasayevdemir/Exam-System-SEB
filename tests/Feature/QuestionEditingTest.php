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
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A question with $texts as its options, the one at $correct marked right.
 *
 * @return array{question: Question, answers: Collection<int, Answer>}
 */
function seedQuestion(array $texts = ['A', 'B', 'C', 'D'], int $correct = 0): array
{
    $question = Question::factory()->create([
        'question_bank_id' => QuestionBank::factory(),
        'question_type' => 'single',
    ]);

    $answers = collect($texts)->map(fn ($text, $i) => Answer::create([
        'question_id' => $question->id,
        'answer_text' => $text,
        'is_correct' => $i === $correct,
    ]));

    return ['question' => $question, 'answers' => $answers];
}

function editQuestion(Question $question, array $payload)
{
    return test()
        ->withSession(['admin_logged_in' => true])
        ->put(route('admin.update-question', $question->id), array_merge([
            'question_text' => $question->question_text,
            'question_type' => 'single',
            'difficulty' => $question->difficulty,
        ], $payload));
}

it('keeps answer ids stable when a question is edited', function () {
    $seed = seedQuestion(['A', 'B', 'C', 'D'], 0);
    $originalIds = $seed['answers']->pluck('id')->all();

    editQuestion($seed['question'], [
        'answers' => ['A revised', 'B', 'C', 'D'],
        'correct_answers' => [0],
    ]);

    $ids = $seed['question']->fresh()->answers()->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe($originalIds);
});

it('does not destroy past student answers when a question is edited', function () {
    $seed = seedQuestion(['A', 'B', 'C', 'D'], 0);
    $user = User::factory()->create();
    $exam = Exam::factory()->create();

    $result = ExamResult::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'total_questions' => 1,
        'correct_answers' => 1,
        'score' => 1,
        'submitted_at' => now(),
    ]);

    StudentAnswer::create([
        'exam_result_id' => $result->id,
        'question_id' => $seed['question']->id,
        'answer_id' => $seed['answers'][0]->id,
    ]);

    editQuestion($seed['question'], [
        'answers' => ['A revised', 'B', 'C', 'D'],
        'correct_answers' => [1],
    ]);

    // student_answers.answer_id cascades on delete, so recreating the answer
    // rows silently wiped every historical submission for this question.
    expect(StudentAnswer::count())->toBe(1);
});

it('applies edited text and correctness to the existing answers', function () {
    $seed = seedQuestion(['A', 'B', 'C', 'D'], 0);

    editQuestion($seed['question'], [
        'answers' => ['A revised', 'B', 'C', 'D'],
        'correct_answers' => [2],
    ]);

    $answers = $seed['question']->fresh()->answers()->orderBy('id')->get();

    expect($answers[0]->answer_text)->toBe('A revised')
        ->and($answers[0]->is_correct)->toBeFalse()
        ->and($answers[2]->is_correct)->toBeTrue();
});

it('adds newly appended options', function () {
    $seed = seedQuestion(['A', 'B'], 0);

    editQuestion($seed['question'], [
        'answers' => ['A', 'B', 'C'],
        'correct_answers' => [0],
    ]);

    expect($seed['question']->fresh()->answers)->toHaveCount(3);
});

it('drops surplus options that were never used', function () {
    $seed = seedQuestion(['A', 'B', 'C', 'D'], 0);

    editQuestion($seed['question'], [
        'answers' => ['A', 'B'],
        'correct_answers' => [0],
    ]);

    expect($seed['question']->fresh()->answers)->toHaveCount(2);
});

it('refuses to drop an option a student has already chosen', function () {
    $seed = seedQuestion(['A', 'B', 'C', 'D'], 0);
    $user = User::factory()->create();
    $exam = Exam::factory()->create();

    $result = ExamResult::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'total_questions' => 1,
        'correct_answers' => 0,
        'score' => 0,
        'submitted_at' => now(),
    ]);

    // Someone picked option D, which the edit below tries to remove.
    StudentAnswer::create([
        'exam_result_id' => $result->id,
        'question_id' => $seed['question']->id,
        'answer_id' => $seed['answers'][3]->id,
    ]);

    editQuestion($seed['question'], [
        'answers' => ['A', 'B', 'C'],
        'correct_answers' => [0],
    ]);

    expect($seed['question']->fresh()->answers)->toHaveCount(4)
        ->and(StudentAnswer::count())->toBe(1);
});

it('keeps the correct answer on the right option when an edit blanks a middle option', function () {
    // Same index-remap hazard as creation: the form posts positions in its own
    // array, so a blank in the middle must not shift which option is correct.
    $seed = seedQuestion(['Alpha', 'Beta', 'Gamma', 'Delta'], 0);

    editQuestion($seed['question'], [
        'answers' => ['Alpha', 'Beta', '', 'Delta'],
        'correct_answers' => [3], // the admin clicked "Delta"
    ]);

    $answers = $seed['question']->fresh()->answers()->orderBy('id')->get();

    expect($answers->where('is_correct', true)->pluck('answer_text')->values()->all())->toBe(['Delta']);
});

it('refuses an edit that leaves a single choice question with two correct answers', function () {
    $seed = seedQuestion(['Alpha', 'Beta', 'Gamma', 'Delta'], 0);

    editQuestion($seed['question'], [
        'answers' => ['Alpha', 'Beta', 'Gamma', 'Delta'],
        'correct_answers' => [0, 2],
    ])->assertSessionHasErrors('correct_answers');

    $answers = $seed['question']->fresh()->answers()->orderBy('id')->get();

    expect($answers->where('is_correct', true))->toHaveCount(1);
});
