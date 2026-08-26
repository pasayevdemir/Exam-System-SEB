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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, Answer> $answers
 * @property-read QuestionBank|null $questionBank
 */
class Question extends Model
{
    use HasFactory;

    public const WEIGHTS = [
        'easy' => 1.0,
        'medium' => 1.5,
        'hard' => 2.0,
    ];

    protected $fillable = [
        'question_bank_id',
        'question_text',
        'question_type',
        'difficulty',
        'file_upload_settings',
    ];

    protected $casts = [
        'file_upload_settings' => 'array',
    ];

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function attemptQuestions()
    {
        return $this->hasMany(ExamAttemptQuestion::class);
    }

    public function getWeight(): float
    {
        return self::WEIGHTS[$this->difficulty] ?? self::WEIGHTS['medium'];
    }

    /**
     * True once this question has been served in a generated attempt, even if unanswered.
     */
    public function hasBeenServed(): bool
    {
        return $this->attemptQuestions()->exists();
    }

    /**
     * Check if this question is a file upload type
     */
    public function isFileUpload()
    {
        return $this->question_type === 'file_upload';
    }

    /**
     * Check if this question is an MCQ type (single or multiple)
     */
    public function isMCQ()
    {
        return in_array($this->question_type, ['single', 'multiple']);
    }

    /**
     * Get allowed file extensions for file upload questions
     */
    public function getAllowedExtensions()
    {
        if (! $this->isFileUpload() || ! $this->file_upload_settings) {
            return [];
        }

        return $this->file_upload_settings['allowed_extensions'] ?? ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    }

    /**
     * Get maximum file size in MB for file upload questions
     */
    public function getMaxFileSize()
    {
        if (! $this->isFileUpload() || ! $this->file_upload_settings) {
            return 10; // Default 10MB
        }

        return $this->file_upload_settings['max_size_mb'] ?? 10;
    }
}
