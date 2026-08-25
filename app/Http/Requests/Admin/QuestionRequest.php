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

/**
 * Creating a question and editing one are the same form with the same rules, so
 * both actions share this class rather than a Store/Update pair that would
 * differ in nothing.
 *
 * The two inline copies this replaces had drifted by exactly one character of
 * meaning - answers.* was `required` on create and `nullable` on edit - and
 * neither spelling could ever show, because prepareForValidation() removes every
 * blank option before a rule runs. The stricter one is kept.
 */
class QuestionRequest extends FormRequest
{
    /**
     * The admin panel is gated by AdminMiddleware, so by the time a request
     * class runs the caller is already an admin.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop the option boxes the admin left blank, and move the correct-answer
     * positions to match.
     *
     * The form renders a fixed number of option boxes, so a two-option question
     * arrives with empty strings in the remaining ones. correct_answers holds
     * positions in the array *as submitted*, which is why the mapping is built
     * from that array and not from the filtered result: comparing form positions
     * against an already-filtered list silently marks the wrong option correct.
     *
     * A correct_answers entry pointing at a box that turned out to be blank has
     * nothing left to point at, so it is dropped - and the size/min rule below
     * then rejects a question left with no correct answer at all.
     */
    protected function prepareForValidation(): void
    {
        $submitted = $this->input('answers');

        if (! is_array($submitted)) {
            return;
        }

        $moved = [];
        $kept = [];

        foreach ($submitted as $position => $answer) {
            if (is_string($answer) && trim($answer) !== '') {
                $moved[$position] = count($kept);
                $kept[] = $answer;
            }
        }

        $merged = ['answers' => $kept];
        $correct = $this->input('correct_answers');

        if (is_array($correct)) {
            $merged['correct_answers'] = array_values(array_filter(
                array_map(fn ($position) => $moved[$position] ?? null, $correct),
                fn ($position) => $position !== null
            ));
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:single,multiple,file_upload',
            'difficulty' => 'required|in:easy,medium,hard',
        ];

        if ($this->input('question_type') === 'file_upload') {
            return $rules + [
                'file_upload_settings.allowed_extensions' => 'nullable|array',
                'file_upload_settings.allowed_extensions.*' => 'string|in:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt',
                'file_upload_settings.max_size_mb' => 'nullable|integer|min:1|max:100',
            ];
        }

        return $rules + [
            'answers' => 'required|array|min:2',
            'answers.*' => 'required|string',
            // A single-choice question with two correct answers is unscoreable.
            // Only the form's JS enforced this, so anything that did not come
            // through that form could store one - and editing must not be a way
            // to reach the state creating one already refuses.
            'correct_answers' => $this->input('question_type') === 'single'
                ? 'required|array|size:1'
                : 'required|array|min:1',
            'correct_answers.*' => 'required|integer',
        ];
    }
}
