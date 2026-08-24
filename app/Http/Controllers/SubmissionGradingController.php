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

use App\Http\Requests\Admin\GradeFileSubmissionRequest;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\Storage;

/**
 * The manual half of scoring.
 *
 * File-upload answers carry no correct option to compare against, so they stay
 * unscored until an admin opens the file and marks them by hand.
 */
class SubmissionGradingController extends Controller
{
    public function gradeFileSubmission(GradeFileSubmissionRequest $request, $studentAnswerId)
    {

        $studentAnswer = StudentAnswer::with(['question', 'examResult.exam'])->findOrFail($studentAnswerId);

        // Ensure this is a file upload question
        if (! $studentAnswer->question->isFileUpload()) {
            return back()->with('error', 'This is not a file upload question.');
        }

        $studentAnswer->update([
            'manual_score' => $request->manual_score,
            'admin_feedback' => $request->admin_feedback,
            'is_graded' => true,
        ]);

        $examResult = $studentAnswer->examResult;
        if (! $examResult->hasGradingPending()) {
            $examResult->recalculateScore();
        }

        return back()->with('success', 'File submission graded successfully!');
    }

    public function downloadSubmission($studentAnswerId)
    {
        $studentAnswer = StudentAnswer::findOrFail($studentAnswerId);

        abort_unless($studentAnswer->file_path, 404);
        abort_unless(Storage::disk('local')->exists($studentAnswer->file_path), 404);

        return Storage::disk('local')->download(
            $studentAnswer->file_path,
            $studentAnswer->original_filename
        );
    }

    public function gradeSubmissions($examId)
    {
        $exam = Exam::findOrFail($examId);

        // Get all submissions with file uploads
        $submissions = ExamResult::where('exam_id', $examId)
            ->with(['user', 'studentAnswers' => function ($query) {
                $query->whereNotNull('file_path')
                    ->with('question');
            }])
            ->whereHas('studentAnswers', function ($query) {
                $query->whereNotNull('file_path');
            })
            ->orderBy('submitted_at', 'desc')
            ->get();

        // Derive distinct file-upload questions from the submissions actually served,
        // since questions no longer belong to a single exam.
        $fileUploadQuestions = $submissions
            ->flatMap(fn ($submission) => $submission->studentAnswers->pluck('question'))
            ->unique('id')
            ->values();

        if ($fileUploadQuestions->isEmpty()) {
            return redirect()->route('admin.exam-results', $examId)
                ->with('error', 'This exam has no file upload questions to grade.');
        }

        return view('admin.grade-submissions', compact('exam', 'fileUploadQuestions', 'submissions'));
    }
}
