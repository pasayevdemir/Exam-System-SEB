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

use App\Models\Exam;

/**
 * The admin landing page: the counts and recent activity an admin sees first.
 */
class DashboardController extends Controller
{
    public function dashboard()
    {
        // sitting_count drives the Deactivate button's disabled state, so it has
        // to be counted the same way toggleExamStatus refuses: inProgress(), not
        // a plain attempt count.
        $exams = Exam::with('examQuestionBanks')
            ->withCount(['attempts as sitting_count' => fn ($q) => $q->inProgress()])
            ->orderBy('created_at', 'desc')
            ->paginate(6);

        foreach ($exams as $exam) {
            $exam->quota_total = $exam->examQuestionBanks->sum(function ($eqb) {
                return $eqb->quota_easy + $eqb->quota_medium + $eqb->quota_hard;
            });
        }

        return view('admin.dashboard', compact('exams'));
    }
}
