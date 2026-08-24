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

use App\Http\Requests\Admin\StoreQuestionBankRequest;
use App\Http\Requests\Admin\UpdateQuestionBankRequest;
use App\Models\Exam;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Services\AdminCredentials;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Question banks as containers: named, described, listed, deleted.
 *
 * The questions inside a bank belong to BankQuestionController, and loading
 * them in bulk to QuestionImportController.
 */
class QuestionBankController extends Controller
{
    public function __construct(private readonly AdminCredentials $credentials) {}

    public function banks()
    {
        $banks = QuestionBank::withCount('questions')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.banks', compact('banks'));
    }

    public function createBank()
    {
        return view('admin.create-bank');
    }

    public function storeBank(StoreQuestionBankRequest $request)
    {

        $bank = QuestionBank::create($request->only(['name', 'description']));

        return redirect()->route('admin.bank-questions', $bank->id)
            ->with('success', 'Question bank created successfully!');
    }

    public function editBank($bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);

        return view('admin.edit-bank', compact('bank'));
    }

    public function updateBank(UpdateQuestionBankRequest $request, $bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);

        $bank->update($request->only(['name', 'description']));

        return redirect()->route('admin.banks')->with('success', 'Question bank updated successfully!');
    }

    /**
     * Delete a bank together with its questions, gated behind the admin password.
     *
     * Having questions or being attached to an exam no longer blocks deletion —
     * the questions are removed and the exam attachments detached as part of the
     * same transaction. What still blocks it is student history: once a question
     * has been served in an attempt or answered, deleting it would orphan real
     * results, so the bank is kept and the admin is told why.
     */
    public function deleteBank(Request $request, $bankId)
    {
        $bank = QuestionBank::withCount('questions')->findOrFail($bankId);

        if (! $this->credentials->passwordMatches($request->input('admin_password'))) {
            return redirect()->route('admin.banks')
                ->with('error', 'Incorrect admin password. The bank was not deleted.');
        }

        $questionIds = $bank->questions()->pluck('id');

        $servedCount = ExamAttemptQuestion::whereIn('question_id', $questionIds)->count();
        $answeredCount = StudentAnswer::whereIn('question_id', $questionIds)->count();

        if ($servedCount > 0 || $answeredCount > 0) {
            return redirect()->route('admin.banks')->with(
                'error',
                "Cannot delete \"{$bank->name}\": its questions are part of student history "
                ."({$servedCount} served in exam attempts, {$answeredCount} answered). "
                .'Deleting it would orphan existing results.'
            );
        }

        $attachedExamIds = $bank->exams()->pluck('exams.id');
        $deactivated = 0;

        try {
            DB::transaction(function () use ($bank, $questionIds, $attachedExamIds, &$deactivated) {
                // Both are RESTRICT in the schema, so they must go before the bank itself.
                // Answers hang off questions with ON DELETE CASCADE and need no explicit pass.
                $bank->exams()->detach();
                Question::whereIn('id', $questionIds)->delete();
                $bank->delete();

                // An exam draws its questions solely from its banks, so one left with
                // none would generate an empty attempt — a student would be handed a
                // timed exam with zero questions and scored 0/0. Park those instead.
                $deactivated = Exam::whereIn('id', $attachedExamIds)
                    ->where('is_active', true)
                    ->whereDoesntHave('examQuestionBanks')
                    ->update(['is_active' => false]);
            });
        } catch (QueryException $e) {
            // The history guards above are read outside the transaction, so an
            // attempt generated in between still trips a RESTRICT foreign key.
            // Report that as the same refusal rather than a 500.
            return redirect()->route('admin.banks')->with(
                'error',
                "Could not delete \"{$bank->name}\": a student attempt started while the deletion was in progress."
            );
        }

        $detail = "{$bank->questions_count} question(s) removed";
        if ($attachedExamIds->isNotEmpty()) {
            $detail .= ", detached from {$attachedExamIds->count()} exam(s)";
        }
        if ($deactivated > 0) {
            $detail .= ", {$deactivated} exam(s) deactivated for having no banks left";
        }

        return redirect()->route('admin.banks')
            ->with('success', "Question bank \"{$bank->name}\" deleted — {$detail}.");
    }
}
