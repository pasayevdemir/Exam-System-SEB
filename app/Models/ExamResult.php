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

class ExamResult extends Model
{
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
        'score' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function examAttempt()
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function studentAnswers()
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
     * Recompute score/correct_answers from current answers: 1 point per correct MCQ
     * question (single: chosen answer is_correct; multiple: selected set matches the
     * correct set exactly), plus 1 point per file-upload answer graded manual_score
     * >= 50. Only meaningful once hasGradingPending() is false - call after each
     * manual grade. Mirrors the scoring rules in StudentController::submitExam().
     */
    public function recalculateScore(): void
    {
        $answers = $this->studentAnswers()->with(['question.answers', 'answer'])->get();
        $byQuestion = $answers->groupBy('question_id');

        $correctAnswers = 0;

        foreach ($byQuestion as $questionAnswers) {
            $question = $questionAnswers->first()->question;

            if ($question->question_type === 'file_upload') {
                $studentAnswer = $questionAnswers->first();
                if ($studentAnswer->is_graded && $studentAnswer->manual_score >= 50) {
                    $correctAnswers++;
                }
            } elseif ($question->question_type === 'single') {
                $studentAnswer = $questionAnswers->first();
                // The stored answer must belong to this question, mirroring the
                // guard in StudentController::submitExam() - otherwise a manual
                // file grade would recalculate the exploit straight back in.
                if ($studentAnswer->answer
                    && $studentAnswer->answer->question_id === $question->id
                    && $studentAnswer->answer->is_correct) {
                    $correctAnswers++;
                }
            } else { // multiple
                $selectedAnswerIds = $questionAnswers->pluck('answer_id')->filter()->sort()->values()->toArray();
                $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->sort()->values()->toArray();
                if ($selectedAnswerIds === $correctAnswerIds) {
                    $correctAnswers++;
                }
            }
        }

        $this->update([
            'correct_answers' => $correctAnswers,
            'score' => $correctAnswers,
        ]);
    }
}
