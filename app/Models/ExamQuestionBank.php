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

class ExamQuestionBank extends Model
{
    protected $table = 'exam_question_bank';

    protected $fillable = [
        'exam_id',
        'question_bank_id',
        'quota_easy',
        'quota_medium',
        'quota_hard',
        'sort_order',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }
}
