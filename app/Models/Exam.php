<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'exam_name',
        'description',
        'is_active',
        'time_limit_minutes',
        'entry_password'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function requiresEntryPassword(): bool
    {
        return filled($this->entry_password);
    }

    public function examQuestionBanks()
    {
        return $this->hasMany(ExamQuestionBank::class);
    }

    public function questionBanks()
    {
        return $this->belongsToMany(QuestionBank::class, 'exam_question_bank')
            ->withPivot(['quota_easy', 'quota_medium', 'quota_hard', 'sort_order'])
            ->withTimestamps();
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }
}
