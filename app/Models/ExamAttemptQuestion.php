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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property-read Question $question
 * @property-read ExamAttempt $examAttempt
 * @property-read ExamAttemptAnswer|null $draftAnswer
 */
class ExamAttemptQuestion extends Model
{
    protected $fillable = [
        'exam_attempt_id',
        'question_id',
        'display_order',
        'weight_at_generation',
        'answer_display_order',
    ];

    protected $casts = [
        'answer_display_order' => 'array',
        'weight_at_generation' => 'decimal:2',
    ];

    public function examAttempt()
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function draftAnswer()
    {
        return $this->hasOne(ExamAttemptAnswer::class);
    }

    /**
     * This attempt's pinned option order, with any since-deleted answers dropped
     * and the survivors renumbered from zero.
     *
     * The exam page submits an option's position rather than its id, so the view
     * and the submit handler have to agree on what position N means. Both read it
     * from here - if one filtered and the other did not, deleting an option mid
     * attempt would silently shift positions and credit the wrong answer.
     *
     * @return Collection<int, Answer>
     */
    public function orderedAnswers(): Collection
    {
        $answersById = $this->question->answers->keyBy('id');

        return collect($this->answer_display_order ?? [])
            ->map(fn ($answerId) => $answersById->get($answerId))
            ->filter()
            ->values();
    }
}
