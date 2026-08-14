<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PasswordResetRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Badge count for the admin navbar. Kept here so the partial does not carry
     * a query of its own.
     */
    public static function pendingCount(): int
    {
        return static::pending()->count();
    }

    /**
     * Resolve any open request for a student once their password has actually
     * been changed — whether an admin set it or the student changed it from
     * their own profile. A request left pending after the fact keeps burning in
     * the admin queue and in the navbar badge forever.
     */
    public static function closePendingFor(User $student): void
    {
        static::where('user_id', $student->id)
            ->pending()
            ->update([
                'status' => self::STATUS_APPROVED,
                'resolved_at' => now(),
            ]);
    }
}
