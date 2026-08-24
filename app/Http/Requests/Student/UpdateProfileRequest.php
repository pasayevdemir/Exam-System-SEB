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
use Illuminate\Validation\Rule;

/**
 * Once the FIN code is locked it drops out of the rules entirely, so validated()
 * never carries it. The form renders the field as disabled, but that is only a
 * hint to a browser - this is what actually stops a hand-crafted POST rewriting
 * the national ID a student's issued results are tied to.
 */
class UpdateProfileRequest extends FormRequest
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
        $user = $this->user();

        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        if (! $user->finCodeIsLocked()) {
            $rules['fin_code'] = ['required', 'string', 'max:20', Rule::unique('users', 'fin_code')->ignore($user->id)];
        }

        return $rules;
    }
}
