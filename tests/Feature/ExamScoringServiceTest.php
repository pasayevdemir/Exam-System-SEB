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
 * The scoring rules, exercised directly rather than through a submit request.
 *
 * ExamScoringTest already proves the end-to-end result, but only ever from the
 * outside: it can see that a score came out as 1, not which of the rules
 * produced it. These reach the rules themselves, so a change to any one of them
 * - the position-to-id resolution, the all-or-nothing multiple choice, the
 * expired-attempt path reading drafts instead of the request - fails on its own
 * terms.
 */

use App\Models\ExamAttemptAnswer;
use App\Models\User;
use App\Services\ExamScoringService;

function scoring(): ExamScoringService
{
    return app(ExamScoringService::class);
}

describe('submission rules', function () {
    it('bounds a single-choice answer by the option count, not by answer id', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 4, 'correct' => [0]]]);
        $questionId = $seed['questions'][0]['question']->id;

        $rules = scoring()->rulesFor(scoring()->questionsFor($seed['attempt']));

        expect($rules["answers.{$questionId}"])->toBe('nullable|integer|min:0|max:3');
    });

    it('lets a multiple-choice answer be an array of positions', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'multiple', 'options' => 3, 'correct' => [0, 1]]]);
        $questionId = $seed['questions'][0]['question']->id;

        $rules = scoring()->rulesFor(scoring()->questionsFor($seed['attempt']));

        expect($rules["answers.{$questionId}"])->toBe('nullable|array')
            ->and($rules["answers.{$questionId}.*"])->toBe('integer|min:0|max:2');
    });

    it('turns a file-upload question into a mime and size rule', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'file_upload']]);
        $questionId = $seed['questions'][0]['question']->id;

        $rules = scoring()->rulesFor(scoring()->questionsFor($seed['attempt']));

        expect($rules["file_uploads.{$questionId}"])->toBe('nullable|file|mimes:pdf|max:10240');
    });

    // Handing in a partly blank paper is allowed, exactly like on paper.
    it('makes every answer nullable', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [
            ['type' => 'single', 'options' => 2, 'correct' => [0]],
            ['type' => 'multiple', 'options' => 2, 'correct' => [0]],
        ]);

        $rules = scoring()->rulesFor(scoring()->questionsFor($seed['attempt']));

        foreach ($rules as $key => $rule) {
            if (! str_ends_with($key, '.*')) {
                expect($rule)->toContain('nullable');
            }
        }
    });
});

describe('resolving positions to answers', function () {
    // The page only ever sees positions, so an answer id is never guessable from
    // the markup - and a position is looked up in its own question's order.
    it('reads a position against that question its own shuffled order', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 4, 'correct' => [0]]]);
        $answers = $seed['questions'][0]['answers'];
        $questionId = $seed['questions'][0]['question']->id;

        // Reverse the pinned order: position 0 must now mean the last answer.
        $seed['attempt']->attemptQuestions()->first()->update([
            'answer_display_order' => array_reverse(array_map(fn ($a) => $a->id, $answers)),
        ]);

        $resolved = scoring()->resolveAnswers(
            scoring()->questionsFor($seed['attempt']),
            [$questionId => 0],
            false
        );

        expect($resolved[$questionId])->toBe($answers[3]->id);
    });

    it('drops a position outside the question option range', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'multiple', 'options' => 3, 'correct' => [0]]]);
        $questionId = $seed['questions'][0]['question']->id;

        $resolved = scoring()->resolveAnswers(
            scoring()->questionsFor($seed['attempt']),
            [$questionId => [0, 99]],
            false
        );

        expect($resolved[$questionId])->toHaveCount(1);
    });

    // A question id the student was never served used to dereference null and
    // 500 before any scoring ran.
    it('ignores an answer for a question this attempt never served', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 2, 'correct' => [0]]]);

        $resolved = scoring()->resolveAnswers(
            scoring()->questionsFor($seed['attempt']),
            [999999 => 0],
            false
        );

        expect($resolved)->toBeEmpty();
    });

    it('discards a duplicated position rather than counting it twice', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'multiple', 'options' => 3, 'correct' => [0]]]);
        $questionId = $seed['questions'][0]['question']->id;

        $resolved = scoring()->resolveAnswers(
            scoring()->questionsFor($seed['attempt']),
            [$questionId => [1, 1, 1]],
            false
        );

        expect($resolved[$questionId])->toHaveCount(1);
    });
});

// Once the deadline passes, the request body stops counting entirely. This is
// what stops a manipulated client replaying or editing answers after time is up.
describe('an expired attempt', function () {
    it('scores from the autosaved draft and ignores the request body', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 4, 'correct' => [2]]]);
        $question = $seed['questions'][0]['question'];
        $answers = $seed['questions'][0]['answers'];

        ExamAttemptAnswer::create([
            'exam_attempt_id' => $seed['attempt']->id,
            'exam_attempt_question_id' => $seed['attempt']->attemptQuestions()
                ->where('question_id', $question->id)->value('id'),
            'selected_answer_ids' => [$answers[2]->id],
        ]);

        // The body claims a different answer; expiry means it is not consulted.
        $resolved = scoring()->resolveAnswers(
            scoring()->questionsFor($seed['attempt']),
            [$question->id => 0],
            true
        );

        expect($resolved[$question->id])->toBe($answers[2]->id);
    });

    it('treats a question with no draft as unanswered', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 3, 'correct' => [0]]]);
        $questionId = $seed['questions'][0]['question']->id;

        $resolved = scoring()->resolveAnswers(
            scoring()->questionsFor($seed['attempt']),
            [],
            true
        );

        expect($resolved[$questionId])->toBeNull();
    });
});

describe('counting correct answers', function () {
    it('awards a point for the right single-choice answer', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 4, 'correct' => [2]]]);
        $attemptQuestions = scoring()->questionsFor($seed['attempt']);
        $questionId = $seed['questions'][0]['question']->id;
        $answers = $seed['questions'][0]['answers'];

        expect(scoring()->countCorrect($attemptQuestions, collect([$questionId => $answers[2]->id])))->toBe(1)
            ->and(scoring()->countCorrect($attemptQuestions, collect([$questionId => $answers[0]->id])))->toBe(0);
    });

    // Without this, one known-correct id could be replayed across every question.
    it('scores nothing for an answer belonging to another question', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [
            ['type' => 'single', 'options' => 3, 'correct' => [0]],
            ['type' => 'single', 'options' => 3, 'correct' => [0]],
        ]);
        $attemptQuestions = scoring()->questionsFor($seed['attempt']);

        $firstQuestionId = $seed['questions'][0]['question']->id;
        $secondQuestionsCorrectId = $seed['questions'][1]['answers'][0]->id;

        expect(scoring()->countCorrect($attemptQuestions, collect([
            $firstQuestionId => $secondQuestionsCorrectId,
        ])))->toBe(0);
    });

    it('gives multiple choice a point only for an exact match', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'multiple', 'options' => 4, 'correct' => [0, 2]]]);
        $attemptQuestions = scoring()->questionsFor($seed['attempt']);
        $questionId = $seed['questions'][0]['question']->id;
        $a = $seed['questions'][0]['answers'];

        $count = fn (array $ids) => scoring()->countCorrect($attemptQuestions, collect([$questionId => $ids]));

        expect($count([$a[0]->id, $a[2]->id]))->toBe(1)
            ->and($count([$a[2]->id, $a[0]->id]))->toBe(1)   // order must not matter
            ->and($count([$a[0]->id]))->toBe(0)              // one correct option missing
            ->and($count([$a[0]->id, $a[2]->id, $a[1]->id]))->toBe(0); // one wrong option added
    });

    it('scores an unanswered question as zero rather than failing', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [['type' => 'single', 'options' => 3, 'correct' => [0]]]);
        $questionId = $seed['questions'][0]['question']->id;

        expect(scoring()->countCorrect(
            scoring()->questionsFor($seed['attempt']),
            collect([$questionId => null])
        ))->toBe(0);
    });
});

// File uploads are graded by hand, so they must never reach the automatic
// score. Both routes in matter: the request body on a live attempt, and the
// autosaved drafts on an expired one. A file-upload question has no options, so
// an empty selection matched an empty set of correct options exactly - see the
// HTTP-level regression in ExamScoringTest for what that used to be worth.
describe('file-upload questions', function () {
    it('ignores an answers entry posted for a file-upload question', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [
            ['type' => 'single', 'options' => 2, 'correct' => [0]],
            ['type' => 'file_upload'],
        ]);
        $attemptQuestions = scoring()->questionsFor($seed['attempt']);
        $fileQuestionId = $seed['questions'][1]['question']->id;

        $resolved = scoring()->resolveAnswers($attemptQuestions, [$fileQuestionId => 0], false);

        expect($resolved)->toBeEmpty()
            ->and(scoring()->countCorrect($attemptQuestions, $resolved))->toBe(0);
    });

    it('leaves file-upload questions out of an expired attempt drafts', function () {
        $user = User::factory()->create();
        $seed = seedAttempt($user, [
            ['type' => 'single', 'options' => 2, 'correct' => [0]],
            ['type' => 'file_upload'],
        ]);
        $fileQuestionId = $seed['questions'][1]['question']->id;

        $resolved = scoring()->resolveAnswers(scoring()->questionsFor($seed['attempt']), [], true);

        expect($resolved)->not->toHaveKey($fileQuestionId);
    });
});
