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

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'started_at',
        'target_weight',
        'expires_at',
        'completed_at',
        'superseded_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'superseded_at' => 'datetime',
        'target_weight' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('superseded_at');
    }

    /**
     * Attempts a student is still genuinely sitting: not superseded, not
     * submitted, and not past its expiry.
     *
     * The expiry arm mirrors isExpired() in SQL, grace period included, so an
     * abandoned timed attempt stops counting once its clock runs out - otherwise
     * walking away from one exam would lock a student out of every other exam
     * for good. An untimed attempt has no clock to run out, so it does keep
     * blocking until it is submitted or an admin supersedes it.
     */
    public function scopeInProgress($query)
    {
        $grace = config('exam.grace_period_seconds', 30);

        return $query->active()
            ->whereNull('completed_at')
            ->where(function ($q) use ($grace) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now()->subSeconds($grace));
            });
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attemptQuestions()
    {
        return $this->hasMany(ExamAttemptQuestion::class)->orderBy('display_order');
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_attempt_questions')
            ->withPivot(['display_order', 'weight_at_generation', 'answer_display_order'])
            ->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(ExamAttemptEvent::class)->orderBy('occurred_at');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        // now()->subSeconds(...) rather than expires_at->addSeconds(...) - Carbon
        // instances are mutable, and expires_at is a cached attribute reused on
        // every call, so mutating it here would compound the grace period on
        // each subsequent call within the same request/model instance.
        $grace = config('exam.grace_period_seconds', 30);

        return now()->subSeconds($grace)->greaterThanOrEqualTo($this->expires_at);
    }
}
