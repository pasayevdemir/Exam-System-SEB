<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SetStudentPasswordRequest extends FormRequest
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
        // The "fin" mode sets the password to the student's own FIN code and
        // carries no password field at all, so requiring one here would reject
        // the form the admin actually submitted.
        if ($this->input('mode') === 'fin') {
            return [];
        }

        return [
            'password' => 'required|string|min:8',
        ];
    }
}
