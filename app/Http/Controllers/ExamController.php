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

use App\Http\Requests\Admin\StoreExamRequest;
use App\Http\Requests\Admin\UpdateExamRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\AdminCredentials;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Exams themselves - created, edited, deleted, switched on and off.
 *
 * Which questions an exam draws is ExamBankController's business, and what
 * students scored on it is ExamResultController's.
 */
class ExamController extends Controller
{
    public function __construct(private readonly AdminCredentials $credentials) {}

    public function createExam()
    {
        return view('admin.create-exam');
    }

    public function storeExam(StoreExamRequest $request)
    {

        $exam = Exam::create([
            'exam_id' => $request->exam_id,
            'exam_name' => $request->exam_name,
            'description' => $request->description,
            'is_active' => false,
            'time_limit_minutes' => $request->time_limit_minutes,
            'entry_password' => $request->entry_password,
        ]);

        return redirect()->route('admin.exam-banks', $exam->id);
    }

    public function editExam($examId)
    {
        $exam = Exam::with('examQuestionBanks')->findOrFail($examId);
        $exam->quota_total = $exam->examQuestionBanks->sum(function ($eqb) {
            return $eqb->quota_easy + $eqb->quota_medium + $eqb->quota_hard;
        });

        return view('admin.edit-exam', compact('exam'));
    }

    public function updateExam(UpdateExamRequest $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $exam->update([
            'exam_id' => $request->exam_id,
            'exam_name' => $request->exam_name,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? true : false,
            'time_limit_minutes' => $request->time_limit_minutes,
            'entry_password' => $request->entry_password,
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Exam updated successfully!');
    }

    /**
     * Activating is always allowed. Deactivating is not, while students are
     * mid-exam: is_active is only read when the exam page loads, so flipping it
     * under a sitting candidate does not stop them cleanly - it just strands the
     * attempt, since they can still answer but can no longer re-enter the page
     * if anything interrupts them. Deactivation means "no new starts", and the
     * only safe moment for it is when nobody is inside.
     */
    public function toggleExamStatus($examId)
    {
        $result = DB::transaction(function () use ($examId) {
            // Locked for the same read-then-write race deleteExam documents: the
            // count below is worthless if a student can start between reading it
            // and writing is_active. ExamGenerationService takes this same row
            // and re-reads is_active under it, so the two orders are the only
            // two outcomes - the start is refused, or the deactivation is.
            $exam = Exam::lockForUpdate()->findOrFail($examId);

            if ($exam->is_active) {
                // inProgress() rather than active(): an expired or abandoned
                // attempt must not block an admin forever. That scope's expiry
                // arm (grace period included) releases those on its own.
                $sitting = ExamAttempt::inProgress()->where('exam_id', $exam->id)->count();

                if ($sitting > 0) {
                    return ['error', "Cannot deactivate \"{$exam->exam_name}\": {$sitting} student(s) "
                        .'are sitting it right now. Wait until they submit, or their time runs out.'];
                }
            }

            $exam->update(['is_active' => ! $exam->is_active]);

            $status = $exam->is_active ? 'activated' : 'deactivated';

            return ['success', "Exam {$status} successfully!"];
        });

        [$key, $message] = $result;

        return redirect()->route('admin.dashboard')->with($key, $message);
    }

    /**
     * Delete an exam, gated behind the admin password.
     *
     * The bank attachments are unregistered first and the exam is then removed —
     * the banks themselves and every question in them survive, since an exam only
     * ever borrows questions from a bank. Student history still blocks deletion:
     * attempts and results reference the exam with ON DELETE RESTRICT, and losing
     * them is not something a password prompt should be able to authorise.
     */
    public function deleteExam(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        if (! $this->credentials->passwordMatches($request->input('admin_password'))) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Incorrect admin password. The exam was not deleted.');
        }

        $attemptCount = $exam->attempts()->count();
        $resultCount = $exam->examResults()->count();

        if ($attemptCount > 0 || $resultCount > 0) {
            return redirect()->route('admin.dashboard')->with(
                'error',
                "Cannot delete \"{$exam->exam_name}\": it has student history "
                ."({$attemptCount} attempt(s), {$resultCount} result(s)). "
                .'Deactivate it instead to hide it from students.'
            );
        }

        $bankCount = $exam->examQuestionBanks()->count();
        $examName = $exam->exam_name;

        try {
            DB::transaction(function () use ($exam) {
                // Unregister the banks explicitly rather than leaning on the pivot's
                // ON DELETE CASCADE, so the intent is visible at the call site.
                $exam->examQuestionBanks()->delete();
                $exam->delete();
            });
        } catch (QueryException $e) {
            // Same race as QuestionBankController::deleteBank: the attempt/result guards are read before
            // the transaction, so a student starting in between hits the RESTRICT
            // foreign key on exam_attempts.exam_id.
            return redirect()->route('admin.dashboard')->with(
                'error',
                "Could not delete \"{$exam->exam_name}\": a student attempt started while the deletion was in progress."
            );
        }

        return redirect()->route('admin.dashboard')->with(
            'success',
            "Exam \"{$examName}\" deleted — {$bankCount} bank(s) unregistered. The banks and their questions were kept."
        );
    }
}
