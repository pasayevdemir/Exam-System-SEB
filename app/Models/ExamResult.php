<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property-read Exam $exam
 * @property-read User|null $user
 * @property-read ExamAttempt|null $examAttempt
 * @property-read Collection<int, StudentAnswer> $studentAnswers
 */
class ExamResult extends Model
{
    /** What a hand-marked file submission has to reach to count as correct. */
    public const PASS_MARK = 50;

    /**
     * What a question is worth when its attempt row is gone - results whose
     * attempt an admin cleared, and rows written before weighting existed. One
     * point per question is exactly what those scores already meant.
     */
    private const FALLBACK_WEIGHT = 1.0;

    protected $fillable = [
        'exam_id',
        'exam_attempt_id',
        'user_id',
        'student_id',
        'index_no',
        'total_questions',
        'correct_answers',
        'score',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    /**
     * @return HasMany<StudentAnswer, $this>
     */
    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    /**
     * True while any file-upload answer on this result has not yet been manually graded.
     * Uses the loaded studentAnswers relation when available to avoid N+1 queries in lists.
     */
    public function hasGradingPending(): bool
    {
        if ($this->relationLoaded('studentAnswers')) {
            return $this->studentAnswers
                ->contains(fn ($answer) => $answer->file_path !== null && ! $answer->is_graded);
        }

        return $this->studentAnswers()
            ->whereNotNull('file_path')
            ->where('is_graded', false)
            ->exists();
    }

    /**
     * The mark a perfect paper would have scored: the summed weight of the
     * questions this student was actually served, pinned at generation time.
     *
     * Falls back to the question count for a result whose attempt is gone, which
     * is what its unweighted score was measured against anyway.
     */
    public function maxScore(): float
    {
        $targetWeight = $this->examAttempt?->target_weight;

        return $targetWeight !== null
            ? (float) $targetWeight
            : (float) $this->total_questions;
    }

    /**
     * A weight or a score as it should read on a page.
     *
     * Weights are halves, so two decimal places is one too many nearly always:
     * "24.5 / 28" rather than "24.50 / 28.00". The trailing zeros go, the
     * significant digits never do.
     */
    public static function formatPoints(mixed $points): string
    {
        $formatted = number_format((float) $points, 2, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }

    /**
     * Recompute score/correct_answers from current answers.
     *
     * A correct question is worth its weight_at_generation - the weight pinned
     * when the paper was generated, never the question's weight now, so editing
     * a question's difficulty afterwards cannot move a mark that has already
     * been awarded. Correct means: single, the chosen answer is_correct;
     * multiple, the selected set matches the correct set exactly; file upload,
     * graded manual_score >= PASS_MARK. Only meaningful once
     * hasGradingPending() is false - call after each manual grade. Mirrors the
     * scoring rules in ExamScoringService::calculateScore().
     */
    public function recalculateScore(): void
    {
        $byQuestion = $this->studentAnswers()
            ->with(['question.answers', 'answer'])
            ->get()
            ->groupBy('question_id');

        $weights = $this->weightsByQuestion();

        $correctAnswers = 0;
        $score = 0.0;

        foreach ($byQuestion as $questionId => $questionAnswers) {
            if (! $this->isQuestionCorrect($questionAnswers->first()->question, $questionAnswers)) {
                continue;
            }

            $correctAnswers++;
            $score += $weights[$questionId] ?? self::FALLBACK_WEIGHT;
        }

        $this->update([
            'correct_answers' => $correctAnswers,
            'score' => round($score, 2),
        ]);
    }

    /**
     * How this sitting went bank by bank, one row per bank the paper drew from.
     *
     * The denominator comes from the attempt, not from the answer rows: a
     * single-choice question left blank stores no StudentAnswer at all, so
     * counting answers would quietly shrink "out of" and flatter the student.
     *
     * Prefers already-loaded relations, so a results page that eager-loads
     * examAttempt.attemptQuestions.question.questionBank and
     * studentAnswers.question.answers pays no query per row.
     *
     * @return SupportCollection<int, array{bank_name: string, correct_count: int, total_count: int, earned_weight: float, max_weight: float, percentage: float}>
     */
    public function getQuestionBankBreakdown(): SupportCollection
    {
        $byQuestion = $this->answersForScoring()->groupBy('question_id');
        $weights = $this->weightsByQuestion();
        $banks = [];

        foreach ($this->servedQuestions($byQuestion) as $questionId => $question) {
            $bank = $question->questionBank;
            $key = $bank->id ?? 0;
            $weight = $weights[$questionId] ?? self::FALLBACK_WEIGHT;

            $banks[$key] ??= [
                'bank_name' => $bank->name ?? 'Unassigned',
                'correct_count' => 0,
                'total_count' => 0,
                'earned_weight' => 0.0,
                'max_weight' => 0.0,
            ];

            $banks[$key]['total_count']++;
            $banks[$key]['max_weight'] += $weight;

            if ($this->isQuestionCorrect($question, $byQuestion->get($questionId) ?? collect())) {
                $banks[$key]['correct_count']++;
                $banks[$key]['earned_weight'] += $weight;
            }
        }

        return collect($banks)
            ->map(function (array $bank) {
                $bank['earned_weight'] = round($bank['earned_weight'], 2);
                $bank['max_weight'] = round($bank['max_weight'], 2);
                $bank['percentage'] = $bank['max_weight'] > 0
                    ? round($bank['earned_weight'] / $bank['max_weight'] * 100, 1)
                    : 0.0;

                return $bank;
            })
            ->sortBy('bank_name')
            ->values();
    }

    /**
     * Every question this sitting covered, keyed by question id.
     *
     * @param  SupportCollection<int, Collection<int, StudentAnswer>>  $byQuestion
     * @return SupportCollection<int, Question>
     */
    private function servedQuestions(SupportCollection $byQuestion): SupportCollection
    {
        $attempt = $this->examAttempt;

        if ($attempt === null) {
            return $byQuestion->map(fn ($answers) => $answers->first()->question);
        }

        $attemptQuestions = $attempt->relationLoaded('attemptQuestions')
            ? $attempt->attemptQuestions
            : $attempt->attemptQuestions()->with('question.questionBank')->get();

        return $attemptQuestions
            ->mapWithKeys(fn (ExamAttemptQuestion $aq) => [(int) $aq->question_id => $aq->question])
            ->filter();
    }

    /**
     * weight_at_generation per question id, empty when the attempt is gone.
     *
     * @return array<int, float>
     */
    private function weightsByQuestion(): array
    {
        $attempt = $this->examAttempt;

        if ($attempt === null) {
            return [];
        }

        $attemptQuestions = $attempt->relationLoaded('attemptQuestions')
            ? $attempt->attemptQuestions
            : $attempt->attemptQuestions()->get(['exam_attempt_id', 'question_id', 'weight_at_generation']);

        return $attemptQuestions
            ->mapWithKeys(fn (ExamAttemptQuestion $aq) => [
                (int) $aq->question_id => (float) $aq->weight_at_generation,
            ])
            ->all();
    }

    /**
     * @return Collection<int, StudentAnswer>
     */
    private function answersForScoring(): Collection
    {
        return $this->relationLoaded('studentAnswers')
            ? $this->studentAnswers
            : $this->studentAnswers()->with(['question.answers', 'question.questionBank', 'answer'])->get();
    }

    /**
     * The one place a question is judged right or wrong, so the score, the bank
     * breakdown and anything added later cannot drift apart.
     *
     * @param  Collection<int, StudentAnswer>|SupportCollection<int, StudentAnswer>  $questionAnswers
     */
    private function isQuestionCorrect(Question $question, $questionAnswers): bool
    {
        if ($questionAnswers->isEmpty()) {
            return false;
        }

        $studentAnswer = $questionAnswers->first();

        if ($question->question_type === 'file_upload') {
            return $studentAnswer->is_graded && $studentAnswer->manual_score >= self::PASS_MARK;
        }

        if ($question->question_type === 'single') {
            // The stored answer must belong to this question, mirroring the
            // guard in ExamScoringService::calculateScore() - otherwise a manual
            // file grade would recalculate the exploit straight back in.
            return $studentAnswer->answer !== null
                && $studentAnswer->answer->question_id === $question->id
                && (bool) $studentAnswer->answer->is_correct;
        }

        $selectedAnswerIds = $questionAnswers->pluck('answer_id')->filter()->sort()->values()->all();
        $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->sort()->values()->all();

        return $selectedAnswerIds === $correctAnswerIds;
    }
}
