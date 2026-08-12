<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    protected $fillable = [
        'exam_result_id',
        'question_id',
        'answer_id',
        'file_path',
        'original_filename',
        'file_size',
        'file_mime_type',
        'manual_score',
        'admin_feedback',
        'is_graded'
    ];

    protected $casts = [
        'is_graded' => 'boolean',
        'manual_score' => 'decimal:2',
        'file_size' => 'integer',
    ];

    public function examResult()
    {
        return $this->belongsTo(ExamResult::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }

    /**
     * Check if this is a file upload answer
     */
    public function isFileUpload()
    {
        return !is_null($this->file_path);
    }

    /**
     * Get the file download URL — routed through an authenticated admin-only
     * download so submissions aren't reachable via a guessable public URL.
     */
    public function getFileUrl()
    {
        if (!$this->file_path) {
            return null;
        }
        return route('admin.download-submission', $this->id);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSize()
    {
        if (!$this->file_size) {
            return null;
        }
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
