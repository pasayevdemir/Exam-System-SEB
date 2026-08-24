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

class ExamAttemptEvent extends Model
{
    public const TYPES = ['tab_hidden', 'focus_lost', 'fullscreen_exit', 'contextmenu', 'copy', 'paste'];

    protected $fillable = [
        'exam_attempt_id',
        'type',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function examAttempt()
    {
        return $this->belongsTo(ExamAttempt::class);
    }
}
