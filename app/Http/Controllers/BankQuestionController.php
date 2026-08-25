<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Controllers;

use App\Http\Requests\Admin\QuestionRequest;
use App\Models\Answer;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\DB;

/**
 * Individual questions and their answer options, within one bank.
 */
class BankQuestionController extends Controller
{
    public function bankQuestions($bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);
        $questions = $bank->questions()->with('answers')->get();

        return view('admin.bank-questions', compact('bank', 'questions'));
    }

    public function storeQuestion(QuestionRequest $request, $bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);

        // Prepare question data
        $questionData = [
            'question_bank_id' => $bank->id,
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'difficulty' => $request->difficulty,
        ];

        // Add file upload settings if it's a file upload question
        if ($request->question_type === 'file_upload') {
            $questionData['file_upload_settings'] = [
                'allowed_extensions' => $request->input('file_upload_settings.allowed_extensions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']),
                'max_size_mb' => $request->input('file_upload_settings.max_size_mb', 10),
            ];
        }

        // One transaction: a question that half-saved would be served to students
        // as an item they cannot answer but which still counts toward their score.
        DB::transaction(function () use ($request, $questionData) {
            $question = Question::create($questionData);

            // Create answers only for MCQ questions
            if (in_array($request->question_type, ['single', 'multiple'])) {
                foreach ($request->answers as $index => $answerText) {
                    Answer::create([
                        'question_id' => $question->id,
                        'answer_text' => $answerText,
                        'is_correct' => in_array($index, $request->correct_answers),
                    ]);
                }
            }
        });

        return redirect()->route('admin.bank-questions', $bankId)->with('success', 'Question added successfully!');
    }

    public function editQuestion($questionId)
    {
        $question = Question::with(['answers', 'questionBank'])->findOrFail($questionId);

        return view('admin.edit-question', compact('question'));
    }

    public function updateQuestion(QuestionRequest $request, $questionId)
    {
        $question = Question::with(['answers', 'questionBank'])->findOrFail($questionId);

        // Prepare update data
        $updateData = [
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'difficulty' => $request->difficulty,
        ];

        // Add file upload settings if it's a file upload question
        if ($request->question_type === 'file_upload') {
            $updateData['file_upload_settings'] = [
                'allowed_extensions' => $request->input('file_upload_settings.allowed_extensions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']),
                'max_size_mb' => $request->input('file_upload_settings.max_size_mb', 10),
            ];
        } else {
            $updateData['file_upload_settings'] = null;
        }

        // Update question
        $question->update($updateData);

        // Handle answers based on question type
        if ($request->question_type === 'file_upload') {
            // Remove all existing answers for file upload questions
            $kept = $this->deleteUnusedAnswers($question->answers()->orderBy('id')->get());
        } else {
            // Update the existing rows in place instead of recreating them.
            // student_answers.answer_id cascades on delete, so rebuilding the set
            // silently wiped every historical submission for this question, and
            // any in-progress attempt's pinned answer_display_order would be left
            // pointing at ids that no longer exist.
            $existing = $question->answers()->orderBy('id')->get()->values();
            $submitted = $request->answers;

            foreach ($submitted as $index => $answerText) {
                $isCorrect = in_array($index, $request->correct_answers);

                if (isset($existing[$index])) {
                    $existing[$index]->update([
                        'answer_text' => $answerText,
                        'is_correct' => $isCorrect,
                    ]);
                } else {
                    Answer::create([
                        'question_id' => $question->id,
                        'answer_text' => $answerText,
                        'is_correct' => $isCorrect,
                    ]);
                }
            }

            $kept = $this->deleteUnusedAnswers($existing->slice(count($submitted)));
        }

        $redirect = redirect()->route('admin.bank-questions', $question->question_bank_id);

        if ($kept->isNotEmpty()) {
            return $redirect->with('warning', 'Question updated, but '.$kept->count().
                ' option(s) were kept because students have already answered with them.');
        }

        return $redirect->with('success', 'Question updated successfully!');
    }

    public function deleteQuestion($questionId)
    {
        $question = Question::with(['questionBank', 'studentAnswers'])->findOrFail($questionId);

        // Block deletion if the question has been answered, or already served in a
        // generated attempt (which can happen before any answer/submission exists).
        if ($question->studentAnswers()->count() > 0 || $question->hasBeenServed()) {
            return redirect()->route('admin.bank-questions', $question->question_bank_id)
                ->with('error', 'Cannot delete question that has been answered by students or served in an exam attempt.');
        }

        // Delete the question (answers will be deleted automatically due to foreign key constraints)
        $bankId = $question->question_bank_id;
        $question->delete();

        return redirect()->route('admin.bank-questions', $bankId)
            ->with('success', 'Question deleted successfully!');
    }

    /**
     * Delete the given answers, keeping any a student has already selected -
     * student_answers.answer_id cascades, so removing one would take the
     * historical submissions with it. Returns the answers that were kept.
     */
    private function deleteUnusedAnswers($answers)
    {
        $kept = collect();

        foreach ($answers as $answer) {
            if ($answer->studentAnswers()->exists()) {
                $kept->push($answer);

                continue;
            }

            $answer->delete();
        }

        return $kept;
    }
}
