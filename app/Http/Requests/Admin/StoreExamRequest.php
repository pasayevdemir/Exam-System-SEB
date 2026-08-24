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

class StoreExamRequest extends FormRequest
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
            'exam_id' => 'required|string|unique:exams,exam_id',
            'exam_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'entry_password' => 'nullable|string|max:255',
        ];
    }
}
