<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class RequestPasswordResetRequest extends FormRequest
{
    /**
     * The admin panel is gated by AdminMiddleware and the student pages by
     * StudentMiddleware, so by the time a request class runs the caller is
     * already the right kind of user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
        ];
    }
}
