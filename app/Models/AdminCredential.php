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

/**
 * The single admin credential, once the admin has set one from the panel.
 *
 * Read through App\Services\AdminCredentials rather than directly — that service
 * owns the "database row wins, otherwise fall back to config" rule that both the
 * login check and the destructive-action re-auth depend on.
 */
class AdminCredential extends Model
{
    protected $fillable = [
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            // Same mechanism the User model uses, so a plaintext password assigned
            // anywhere is hashed on the way in and never stored as typed.
            'password' => 'hashed',
        ];
    }
}
