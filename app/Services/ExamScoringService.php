<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use Illuminate\Support\Collection;

/**
 * Turns a submitted exam into a score.
 *
 * The counterpart of ExamGenerationService: that one decides which questions a
 * student is asked and in what option order, this one decides what their
 * answers are worth. Both are about the exam, not about HTTP, which is why
 * neither takes a Request.
 *
 * Answers travel as *positions* in the attempt's pinned option order rather
 * than as answer ids, so the page never exposes which id is which. Resolving a
 * position back to an id happens against that question's own order, which is
 * what makes it impossible to submit one question's answer for another.
 */
class ExamScoringService
{
    /**
     * The attempt's questions with everything scoring needs already loaded.
     *
     * @return Collection<int, ExamAttemptQuestion>
     */
    public function questionsFor(ExamAttempt $attempt): Collection
    {
        return $attempt->attemptQuestions()->with(['question.answers', 'draftAnswer'])->get();
    }

    /**
     * Validation rules for a submission of this attempt.
     *
     * Every answer is nullable: a student is allowed to hand in a partly blank
     * paper, exactly like on paper, and skipped questions simply score nothing.
     * What is still checked is the shape of anything actually sent - a position
     * outside the question's own option range, or a file of the wrong type or
     * size, is rejected.
     *
     * @param  Collection<int, ExamAttemptQuestion>  $attemptQuestions
     * @return array<string, string>
     */
    public function rulesFor(Collection $attemptQuestions): array
    {
        $rules = [];

        foreach ($attemptQuestions as $attemptQuestion) {
            $question = $attemptQuestion->question;

            if ($question->question_type === 'file_upload') {
                $allowedExtensions = implode(',', $question->getAllowedExtensions());
                $maxSizeKb = $question->getMaxFileSize() * 1024;

                $rules["file_uploads.{$question->id}"] = "nullable|file|mimes:{$allowedExtensions}|max:{$maxSizeKb}";

                continue;
            }

            // The bound is the option count, not an answers.id: what arrives is
            // a position in this attempt's shuffled order.
            $lastPosition = max(0, $attemptQuestion->orderedAnswers()->count() - 1);

            if ($question->question_type === 'single') {
                $rules["answers.{$question->id}"] = "nullable|integer|min:0|max:{$lastPosition}";
            } else {
                $rules["answers.{$question->id}"] = 'nullable|array';
                $rules["answers.{$question->id}.*"] = "integer|min:0|max:{$lastPosition}";
            }
        }

        return $rules;
    }

    /**
     * What the student actually answered, keyed by question id and holding
     * answer ids: a single id for single-choice, an array for multiple.
     *
     * Once the attempt has expired the request body is ignored entirely in
     * favour of the autosaved drafts. autosaveAnswer refuses writes past the
     * deadline, so those drafts are a frozen snapshot from before it - which is
     * what stops a manipulated client replaying or editing answers after time
     * is up.
     *
     * @param  Collection<int, ExamAttemptQuestion>  $attemptQuestions
     * @param  array<int|string, mixed>  $submittedPositions
     * @return Collection<int, mixed>
     */
    public function resolveAnswers(Collection $attemptQuestions, array $submittedPositions, bool $isExpired): Collection
    {
        $mcq = $attemptQuestions->filter(
            fn (ExamAttemptQuestion $aq) => $aq->question->question_type !== 'file_upload'
        );

        if ($isExpired) {
            // selected_answer_ids already holds real answer ids, so there is no
            // position to resolve here.
            return $mcq->mapWithKeys(function (ExamAttemptQuestion $aq) {
                $ids = $aq->draftAnswer->selected_answer_ids ?? [];

                return [
                    (int) $aq->question_id => $aq->question->question_type === 'single' ? ($ids[0] ?? null) : $ids,
                ];
            });
        }

        // Only questions actually served in this attempt count. A question id the
        // student was never given used to dereference null and 500 before any
        // scoring ran.
        $byQuestionId = $mcq->keyBy('question_id');

        return collect($submittedPositions)
            ->only($byQuestionId->keys()->all())
            ->mapWithKeys(function ($positions, $questionId) use ($byQuestionId) {
                $attemptQuestion = $byQuestionId[$questionId];
                $order = $attemptQuestion->orderedAnswers()->pluck('id');

                $answerIds = collect(is_array($positions) ? $positions : [$positions])
                    ->unique()
                    ->map(fn ($position) => $order[$position] ?? null)
                    ->filter()
                    ->values();

                return [
                    (int) $questionId => $attemptQuestion->question->question_type === 'single'
                        ? $answerIds->first()
                        : $answerIds->all(),
                ];
            });
    }

    /**
     * The number of MCQ questions answered correctly.
     *
     * Multiple choice is all-or-nothing: every correct option and no incorrect
     * one. Partial credit would need a rule about what a half-right answer is
     * worth, and there isn't one.
     *
     * @param  Collection<int, ExamAttemptQuestion>  $attemptQuestions
     * @param  Collection<int, mixed>  $resolvedAnswers
     */
    public function countCorrect(Collection $attemptQuestions, Collection $resolvedAnswers): int
    {
        $questionsById = $attemptQuestions->map->question->keyBy('id');
        $correct = 0;

        foreach ($resolvedAnswers as $questionId => $answerData) {
            $question = $questionsById->get($questionId);

            if ($question === null) {
                continue;
            }

            if ($question->question_type === 'single') {
                // Looked up among this question's own options, so a known-correct
                // id from a different question simply is not found.
                $answer = $question->answers->firstWhere('id', $answerData);

                if ($answer && $answer->is_correct) {
                    $correct++;
                }

                continue;
            }

            $selected = collect(is_array($answerData) ? $answerData : [$answerData])
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $expected = $question->answers
                ->where('is_correct', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($selected === $expected) {
                $correct++;
            }
        }

        return $correct;
    }
}
