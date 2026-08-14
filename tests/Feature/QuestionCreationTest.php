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

function addQuestion(QuestionBank $bank, array $payload)
{
    return test()
        ->withSession(['admin_logged_in' => true])
        ->post(route('admin.store-question', $bank->id), array_merge([
            'question_text' => 'Which one is correct?',
            'question_type' => 'single',
            'difficulty' => 'easy',
            'answers' => ['A', 'B', 'C', 'D'],
            'correct_answers' => [1],
        ], $payload));
}

it('creates a question with its answers', function () {
    $bank = QuestionBank::factory()->create();

    addQuestion($bank, [])->assertSessionHas('success');

    $question = Question::firstWhere('question_bank_id', $bank->id);

    expect($question)->not->toBeNull()
        ->and($question->difficulty)->toBe('easy')
        ->and($question->question_type)->toBe('single')
        ->and($question->answers)->toHaveCount(4);
});

it('marks only the chosen answer correct', function () {
    $bank = QuestionBank::factory()->create();

    addQuestion($bank, ['correct_answers' => [2]]);

    $answers = Question::firstWhere('question_bank_id', $bank->id)->answers()->orderBy('id')->get();

    expect($answers[0]->is_correct)->toBeFalse()
        ->and($answers[1]->is_correct)->toBeFalse()
        ->and($answers[2]->is_correct)->toBeTrue()
        ->and($answers[3]->is_correct)->toBeFalse();
});

it('rejects a single choice question with two correct answers', function () {
    $bank = QuestionBank::factory()->create();

    addQuestion($bank, ['correct_answers' => [0, 2]])
        ->assertSessionHasErrors('correct_answers');

    expect(Question::where('question_bank_id', $bank->id)->count())->toBe(0);
});

it('allows several correct answers on a multiple choice question', function () {
    $bank = QuestionBank::factory()->create();

    addQuestion($bank, ['question_type' => 'multiple', 'correct_answers' => [0, 2]])
        ->assertSessionHas('success');

    $answers = Question::firstWhere('question_bank_id', $bank->id)->answers()->orderBy('id')->get();

    expect($answers->where('is_correct', true))->toHaveCount(2);
});

it('keeps the correct answer on the right option when a blank option is left in the middle', function () {
    // The create form always submits four option boxes, so an admin who fills
    // 1, 2 and 4 sends a blank in slot 3. The blank is stripped before saving,
    // and the chosen index has to follow the option it pointed at.
    $bank = QuestionBank::factory()->create();

    addQuestion($bank, [
        'answers' => ['Alpha', 'Beta', '', 'Delta'],
        'correct_answers' => [3], // the admin clicked "Delta"
    ])->assertSessionHas('success');

    $answers = Question::firstWhere('question_bank_id', $bank->id)->answers()->orderBy('id')->get();

    expect($answers->pluck('answer_text')->all())->toBe(['Alpha', 'Beta', 'Delta']);
    expect($answers->where('is_correct', true)->pluck('answer_text')->values()->all())->toBe(['Delta']);
});

it('keeps question creation behind admin auth', function () {
    $bank = QuestionBank::factory()->create();

    test()->post(route('admin.store-question', $bank->id), [])
        ->assertRedirect(route('admin.login'));
});
