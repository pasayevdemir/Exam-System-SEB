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

use App\Http\Requests\Admin\AttachBankRequest;
use App\Http\Requests\Admin\UpdateBankQuotaRequest;
use App\Models\Exam;
use App\Models\ExamQuestionBank;
use App\Models\QuestionBank;

/**
 * Which banks an exam draws from, and how many questions of each difficulty it
 * takes from each of them.
 *
 * Every change here can leave an exam asking for more questions than its banks
 * hold, so each action re-checks the quotas and says so.
 */
class ExamBankController extends Controller
{
    public function examBanks($examId)
    {
        $exam = Exam::with('examQuestionBanks.questionBank')->findOrFail($examId);
        $availableBanks = QuestionBank::whereNotIn('id', $exam->examQuestionBanks->pluck('question_bank_id'))->get();

        // Actual per-difficulty question counts, so the view can warn about unsatisfiable quotas.
        $bankCounts = QuestionBank::withCount([
            'questions as easy_count' => fn ($q) => $q->where('difficulty', 'easy'),
            'questions as medium_count' => fn ($q) => $q->where('difficulty', 'medium'),
            'questions as hard_count' => fn ($q) => $q->where('difficulty', 'hard'),
        ])->get()->keyBy('id');

        return view('admin.exam-banks', compact('exam', 'availableBanks', 'bankCounts'));
    }

    public function attachBank(AttachBankRequest $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        if (($request->quota_easy + $request->quota_medium + $request->quota_hard) === 0) {
            return back()->with('error', 'At least one difficulty quota must be greater than zero.')->withInput();
        }

        $eqb = ExamQuestionBank::create([
            'exam_id' => $exam->id,
            'question_bank_id' => $request->question_bank_id,
            'quota_easy' => $request->quota_easy,
            'quota_medium' => $request->quota_medium,
            'quota_hard' => $request->quota_hard,
            'sort_order' => $exam->examQuestionBanks()->count(),
        ]);

        return redirect()->route('admin.exam-banks', $exam->id)
            ->with($this->quotaWarningOrSuccess($eqb, 'Bank attached successfully!'));
    }

    public function updateBankQuota(UpdateBankQuotaRequest $request, $examId, $bankAssignmentId)
    {
        $eqb = ExamQuestionBank::where('exam_id', $examId)->findOrFail($bankAssignmentId);

        if (($request->quota_easy + $request->quota_medium + $request->quota_hard) === 0) {
            return back()->with('error', 'At least one difficulty quota must be greater than zero.')->withInput();
        }

        $eqb->update($request->only(['quota_easy', 'quota_medium', 'quota_hard']));

        return redirect()->route('admin.exam-banks', $examId)
            ->with($this->quotaWarningOrSuccess($eqb, 'Quota updated successfully!'));
    }

    public function detachBank($examId, $bankAssignmentId)
    {
        $eqb = ExamQuestionBank::where('exam_id', $examId)->findOrFail($bankAssignmentId);
        $eqb->delete();

        return redirect()->route('admin.exam-banks', $examId)->with('success', 'Bank detached successfully!');
    }

    /**
     * Warn (not block) if a bank's actual per-difficulty question counts already
     * can't satisfy its configured quota, so unsatisfiable configs are caught here
     * rather than at student exam-start time.
     */
    private function quotaWarningOrSuccess(ExamQuestionBank $eqb, string $successMessage): array
    {
        $bank = $eqb->questionBank;
        $shortfalls = [];

        foreach (['easy' => 'quota_easy', 'medium' => 'quota_medium', 'hard' => 'quota_hard'] as $difficulty => $column) {
            $quota = $eqb->$column;
            if ($quota === 0) {
                continue;
            }
            $available = $bank->questions()->where('difficulty', $difficulty)->count();
            if ($available < $quota) {
                $shortfalls[] = "{$difficulty} (needs {$quota}, has {$available})";
            }
        }

        if (! empty($shortfalls)) {
            return ['warning' => "Bank '{$bank->name}' does not yet have enough questions for: ".implode(', ', $shortfalls).'.'];
        }

        return ['success' => $successMessage];
    }
}
