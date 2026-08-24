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

class UpdateBankQuotaRequest extends FormRequest
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
            'quota_easy' => 'required|integer|min:0',
            'quota_medium' => 'required|integer|min:0',
            'quota_hard' => 'required|integer|min:0',
        ];
    }
}
