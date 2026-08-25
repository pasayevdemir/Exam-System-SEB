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

use App\Exports\ExamResultsWorkbook;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Finished attempts from the admin side: the results table, the spreadsheet
 * export of it, and letting a student sit an exam again.
 */
class ExamResultController extends Controller
{
    public function examResults(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $query = ExamResult::where('exam_id', $exam->id)
            ->with('user', 'studentAnswers.question', 'studentAnswers.answer', 'examAttempt.events');

        // Apply search filter if provided - search by student name or FIN code
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('last_name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('fin_code', 'like', '%'.$searchTerm.'%');
            });
        }

        // Get total count for all data (without pagination)
        $totalCount = ExamResult::where('exam_id', $exam->id)->count();

        // Get filtered count for search results
        $filteredCount = $query->count();

        // Calculate average score for all data (not just current page)
        $averageScore = ExamResult::where('exam_id', $exam->id)->avg('score');

        // Apply pagination - 20 items per page
        $results = $query->orderBy('submitted_at', 'desc')->paginate(20);

        // Append search parameters to pagination links
        $results->appends($request->query());

        // Keep search value for the form
        $searchData = [
            'search' => $request->search,
        ];

        return view('admin.exam-results', compact('exam', 'results', 'searchData', 'totalCount', 'filteredCount', 'averageScore'));
    }

    public function allowRetake(ExamResult $examResult)
    {
        $attempt = $examResult->examAttempt;

        if (! $attempt) {
            return back()->with('error', 'This result has no linked exam attempt to reset.');
        }

        if ($attempt->superseded_at !== null) {
            return back()->with('error', 'A retake has already been allowed for this attempt.');
        }

        if ($attempt->completed_at === null) {
            return back()->with('error', "Cannot allow a retake while the student's attempt is still in progress.");
        }

        $attempt->update(['superseded_at' => now()]);

        return back()->with('success', "Retake allowed for {$examResult->user?->name}. They can now restart this exam.");
    }

    public function downloadResults($examId)
    {
        $exam = Exam::findOrFail($examId);

        $results = ExamResult::where('exam_id', $exam->id)
            ->with('user', 'studentAnswers.question.answers', 'studentAnswers.answer')
            ->orderBy('submitted_at', 'desc')
            ->get();

        if ($results->isEmpty()) {
            return redirect()->back()->with('error', 'No results found for this exam.');
        }

        $workbook = new ExamResultsWorkbook($exam, $results);

        // Built before the response starts. Once streamDownload begins writing,
        // the status line has already gone out and a failure can only truncate
        // the file - here it is still a redirect the admin can read.
        try {
            $spreadsheet = $workbook->build();
        } catch (\Throwable $e) {
            Log::error('Excel download error for exam ID '.$examId.': '.$e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while generating the Excel file. Please try again.');
        }

        // A StreamedResponse rather than the header()/exit pair this replaces:
        // exiting mid-request skipped every terminating middleware, session
        // writes included.
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $workbook->filename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
