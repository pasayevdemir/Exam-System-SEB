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

class ImportQuestionsRequest extends FormRequest
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
            // Extension-only on purpose. MIME sniffing for CSV/JSON is unreliable
            // in practice (finfo says text/plain for CSV, Excel uploads arrive as
            // application/vnd.ms-excel, some browsers send octet-stream for .json)
            // and would reject legitimate files while buying no safety here - the
            // parser reads text and never evaluates it.
            'file' => ['required', 'file', 'max:2048', 'extensions:csv,txt,json'],
        ];
    }
}
