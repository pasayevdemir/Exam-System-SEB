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

use App\Http\Requests\Admin\SetStudentPasswordRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Student accounts, and the password-reset requests they raise.
 */
class StudentAdminController extends Controller
{
    public function students(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $students = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('fin_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        $resetRequests = PasswordResetRequest::with('user')
            ->pending()
            ->orderBy('created_at')
            ->get();

        return view('admin.students', compact('students', 'resetRequests', 'search'));
    }

    public function editStudent($userId)
    {
        $student = User::findOrFail($userId);

        return view('admin.edit-student', compact('student'));
    }

    public function updateStudent(UpdateStudentRequest $request, $userId)
    {
        $student = User::findOrFail($userId);

        $validated = $request->validated();

        $student->update($validated);

        return redirect()->route('admin.students')->with('success', 'Student details updated successfully!');
    }

    public function deleteStudent($userId)
    {
        $student = User::findOrFail($userId);

        // Attempts and results carry the student's identity on every score
        // report, so deleting the user out from under them would leave orphaned
        // or misattributed marks. Exam history has to be cleared first.
        if (ExamAttempt::where('user_id', $student->id)->exists()
            || ExamResult::where('user_id', $student->id)->exists()) {
            return redirect()->route('admin.students')
                ->with('error', 'Cannot delete a student who has exam attempts or results on record.');
        }

        $student->delete();

        return redirect()->route('admin.students')->with('success', 'Student deleted successfully!');
    }

    /**
     * Set a student's password directly, either to something the admin typed or
     * to the student's own FIN code. Either way this also closes any reset
     * request the student had open - the request has been answered, so leaving
     * it pending would just make the admin handle it twice.
     */
    public function setStudentPassword(SetStudentPasswordRequest $request, $userId)
    {
        $student = User::findOrFail($userId);

        if ($request->input('mode') === 'fin') {
            $student->update(['password' => $student->fin_code]);
            PasswordResetRequest::closePendingFor($student);

            return back()->with('success', "Password for {$student->name} is now their FIN code ({$student->fin_code}).");
        }

        $student->update(['password' => $request->validated()['password']]);
        PasswordResetRequest::closePendingFor($student);

        // The admin chose this password, so there is nothing to hand back - and
        // echoing it into a flash message would put it in the session store.
        return back()->with('success', "Password updated for {$student->name}.");
    }

    /**
     * Approve a reset request by issuing a generated password. It is shown to
     * the admin exactly once, in a flash message - nothing stores the plaintext,
     * so the admin has to hand it over before leaving the page.
     */
    public function approveResetRequest($requestId)
    {
        $resetRequest = PasswordResetRequest::with('user')->findOrFail($requestId);

        if (! $resetRequest->isPending()) {
            return redirect()->route('admin.students')
                ->with('error', 'That reset request has already been handled.');
        }

        $temporaryPassword = Str::password(12, symbols: false);

        $resetRequest->user->update(['password' => $temporaryPassword]);
        $resetRequest->update([
            'status' => PasswordResetRequest::STATUS_APPROVED,
            'resolved_at' => now(),
        ]);

        return redirect()->route('admin.students')
            ->with('temporary_password', [
                'name' => $resetRequest->user->name,
                'email' => $resetRequest->user->email,
                'password' => $temporaryPassword,
            ]);
    }

    public function rejectResetRequest($requestId)
    {
        $resetRequest = PasswordResetRequest::findOrFail($requestId);

        if (! $resetRequest->isPending()) {
            return redirect()->route('admin.students')
                ->with('error', 'That reset request has already been handled.');
        }

        $resetRequest->update([
            'status' => PasswordResetRequest::STATUS_REJECTED,
            'resolved_at' => now(),
        ]);

        return redirect()->route('admin.students')->with('success', 'Reset request rejected.');
    }
}
