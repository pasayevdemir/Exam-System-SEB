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

use App\Exceptions\ConcurrentExamAttemptException;
use App\Exceptions\ExamClosedException;
use App\Http\Requests\Student\RegisterStudentRequest;
use App\Http\Requests\Student\RequestPasswordResetRequest;
use App\Http\Requests\Student\StudentLoginRequest;
use App\Http\Requests\Student\UpdatePasswordRequest;
use App\Http\Requests\Student\UpdateProfileRequest;
use App\Http\Requests\Student\VerifyExamPasswordRequest;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptEvent;
use App\Models\ExamResult;
use App\Models\PasswordResetRequest;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\ExamGenerationService;
use App\Services\ExamScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function register()
    {
        return view('student.register');
    }

    public function store(RegisterStudentRequest $request)
    {
        $validated = $request->validated();

        // No Hash::make here — the User model casts 'password' => 'hashed', which
        // is the same mechanism every other password write in this app relies on.
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'email' => $validated['email'],
            'fin_code' => $validated['fin_code'],
            'password' => $validated['password'],
        ]);

        // Sign them straight in rather than bouncing them back to a login form
        // with credentials they typed thirty seconds ago.
        Auth::login($user);
        $request->session()->regenerate();

        $response = redirect()->route('student.profile')
            ->with('success', 'Account created successfully! Welcome.');

        return $this->clearAdminSession($request, $response);
    }

    /**
     * Drop an admin session left over in this browser.
     *
     * StudentMiddleware bounces anyone carrying `admin_logged_in`, so without
     * this an admin who registers or signs in as a student from the same browser
     * lands in a redirect loop with no explanation. Note that session()->regenerate()
     * rotates the id but *keeps* the data, so the flag has to be dropped explicitly.
     */
    private function clearAdminSession(Request $request, $response)
    {
        if (! $request->session()->has('admin_logged_in')) {
            return $response;
        }

        $request->session()->forget('admin_logged_in');

        return $response->with('warning', 'You have been signed out of the admin panel in this browser.');
    }

    public function login()
    {
        return view('student.login');
    }

    public function authenticate(StudentLoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return $this->clearAdminSession($request, redirect()->route('student.exams'));
    }

    public function passwordRequest()
    {
        return view('student.password-request');
    }

    /**
     * Raise a password reset request for an admin to approve. Students never
     * reset their own password here - approval hands them a generated one.
     */
    public function storePasswordRequest(RequestPasswordResetRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            // One open request per student: re-submitting must not let a student
            // flood the admin queue, and the admin only ever needs the latest one.
            PasswordResetRequest::firstOrCreate(
                ['user_id' => $user->id, 'status' => PasswordResetRequest::STATUS_PENDING],
            );
        }

        // Same response either way - a differing message would turn this form
        // into a check for which emails are registered.
        return redirect()->route('student.login')->with(
            'success',
            'If that email is registered, your request has been sent. Please contact your exam administrator to collect your new password.'
        );
    }

    /**
     * Everything student.partials.exam-list renders. Shared by the page and by
     * examsState() so the live poll can never drift from the first paint.
     */
    private function examListData(): array
    {
        $completedExamIds = ExamAttempt::active()
            ->where('user_id', Auth::id())
            ->whereNotNull('completed_at')
            ->pluck('exam_id');

        $activeExams = Exam::where('is_active', true)
            ->whereNotIn('id', $completedExamIds)
            ->with('examQuestionBanks')
            ->get();

        // Drives the card states: the exam this belongs to shows Resume, every
        // other card is locked until it is submitted.
        $openAttempt = ExamAttempt::inProgress()
            ->where('user_id', Auth::id())
            ->orderBy('started_at')
            ->first();

        return compact('activeExams', 'openAttempt');
    }

    public function exams()
    {
        return view('student.exams', $this->examListData());
    }

    /**
     * The exam list as the student's open page should now see it.
     *
     * Hashing the rendered partial rather than the model state is deliberate:
     * there is no second definition of "what counts as a change" to keep in step
     * with the view, so an admin editing a name, a time limit, a bank quota or
     * is_active all reach the student through the same one line.
     */
    public function examsState(Request $request)
    {
        return $this->fragmentState($request, 'student.partials.exam-list', $this->examListData());
    }

    /**
     * Shared body of the live-poll endpoints. Answers with the fragment only
     * when the caller's hash is stale, so an idle poll costs a few bytes.
     */
    private function fragmentState(Request $request, string $partial, array $data)
    {
        $html = view($partial, $data)->render();
        $version = sha1($html);

        if ($request->query('v') === $version) {
            return response()->json(['changed' => false]);
        }

        return response()->json([
            'changed' => true,
            'v' => $version,
            'html' => $html,
        ]);
    }

    /**
     * The student's open attempt on some exam other than $exam, if any.
     */
    private function openAttemptOnAnotherExam(Exam $exam): ?ExamAttempt
    {
        return ExamAttempt::inProgress()
            ->where('user_id', Auth::id())
            ->where('exam_id', '!=', $exam->id)
            ->with('exam')
            ->orderBy('started_at')
            ->first();
    }

    public function exam($examId)
    {
        $exam = Exam::findOrFail($examId);

        if (! $exam->is_active) {
            return redirect()->route('student.exams')->with('error', 'This exam is currently not active.');
        }

        $attempt = ExamAttempt::active()
            ->where('exam_id', $exam->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($attempt && $attempt->completed_at !== null) {
            return redirect()->route('student.exams')->with('error', 'You have already completed this exam.');
        }

        // One exam at a time. This blocks resuming a second open attempt as well
        // as starting one, so a student who somehow ended up with two has to
        // finish the older one rather than being free to alternate between them.
        if ($openAttempt = $this->openAttemptOnAnotherExam($exam)) {
            return redirect()->route('student.exams')->with(
                'error',
                "You already have \"{$openAttempt->exam->exam_name}\" in progress. Finish and submit it before starting another exam."
            );
        }

        session(['student_exam_id' => $exam->id]);

        if (! $attempt) {
            if ($exam->requiresEntryPassword() && ! session()->get("exam_password_verified_{$exam->id}")) {
                return view('student.exam-password', compact('exam'));
            }

            try {
                $attempt = app(ExamGenerationService::class)->generate($exam, Auth::user());
            } catch (ConcurrentExamAttemptException $e) {
                // Lost the race against the student's own second request.
                return redirect()->route('student.exams')->with(
                    'error',
                    "You already have \"{$e->openAttempt->exam->exam_name}\" in progress. Finish and submit it before starting another exam."
                );
            } catch (ExamClosedException $e) {
                // An admin deactivated the exam between this request's is_active
                // check and generation taking the row lock. Nothing is wrong -
                // the list will simply no longer show it.
                return redirect()->route('student.exams')->with('error', 'This exam is currently not active.');
            } catch (\RuntimeException $e) {
                \Log::error('Exam generation failed for exam ID '.$exam->id.': '.$e->getMessage());

                return redirect()->route('student.exams')->with('error', 'This exam could not be started right now. Please contact your administrator.');
            }
        }

        $remainingSeconds = $attempt->remainingSeconds();

        // questionBank comes along for the sidebar's per-bank grouping and the
        // bank tag on each question card.
        $questions = $attempt->attemptQuestions()
            ->with(['question.answers', 'question.questionBank', 'draftAnswer'])
            ->get();

        return view('student.exam', compact('exam', 'attempt', 'questions', 'remainingSeconds'));
    }

    /**
     * Lightweight ping from the exam page. Autosave only fires when an answer
     * changes, so a student who sits reading a long question can go idle past
     * SESSION_LIFETIME and be logged out mid-exam. Touching any route refreshes
     * the session; the current token is returned so the page can refresh its
     * forms if the session was rebuilt in the meantime.
     *
     * It doubles as the clock's re-sync channel: the page also pings this when
     * the tab regains focus or the connection comes back, which is exactly when
     * its own countdown is most likely to have drifted.
     */
    public function keepAlive()
    {
        // inProgress() rather than a plain active() lookup: it is the attempt
        // the student is actually sitting, and its expiry arm keeps matching
        // through the grace period - exactly the window where the page is
        // counting down to its auto-submit and most needs the real figure.
        $attempt = ExamAttempt::inProgress()
            ->where('user_id', Auth::id())
            ->with('exam')
            ->first();

        return response()->json([
            'ok' => true,
            'token' => csrf_token(),
            'remaining_seconds' => $attempt?->remainingSeconds(),
            // Defence in depth rather than an expected state: toggleExamStatus
            // refuses to deactivate an exam anyone is sitting, so this only goes
            // false if that guard is bypassed (direct SQL, a future code path).
            // The page shows a banner and keeps accepting answers - a student's
            // half-finished work is not something to discard over it.
            'exam_active' => $attempt?->exam?->is_active,
        ]);
    }

    public function autosaveAnswer(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $attempt = ExamAttempt::active()
            ->where('exam_id', $exam->id)
            ->where('user_id', Auth::id())
            ->whereNull('completed_at')
            ->first();

        if (! $attempt) {
            return response()->json(['success' => false, 'message' => 'No active attempt.'], 409);
        }

        if ($attempt->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Time limit reached.'], 409);
        }

        // Deliberately not a FormRequest: those validate before the method body
        // runs, and the two 409 guards above have to answer first. The exam page
        // treats 409 as final and stops, but retries anything else three times
        // and then queues it forever - so turning an expired attempt into a 422
        // would leave the client hammering a closed attempt.
        $validated = $request->validate([
            'question_id' => 'required|integer',
            'answer_indexes' => 'nullable|array',
            'answer_indexes.*' => 'integer|min:0',
        ]);

        $attemptQuestion = $attempt->attemptQuestions()
            ->where('question_id', $validated['question_id'])
            ->first();

        if (! $attemptQuestion) {
            return response()->json(['success' => false, 'message' => 'Question not part of this attempt.'], 422);
        }

        // The page identifies options by position, so resolve them against this
        // attempt's pinned order. Drafts still store real ids, which is what the
        // exam view checks when it restores a selection.
        $order = $attemptQuestion->orderedAnswers()->pluck('id');
        $answerIds = collect($validated['answer_indexes'] ?? [])
            ->unique()
            ->map(fn ($position) => $order[$position] ?? null)
            ->filter()
            ->values()
            ->all();

        if (empty($answerIds)) {
            ExamAttemptAnswer::where('exam_attempt_id', $attempt->id)
                ->where('exam_attempt_question_id', $attemptQuestion->id)
                ->delete();

            return response()->json([
                'success' => true,
                'cleared' => true,
                'remaining_seconds' => $attempt->remainingSeconds(),
            ]);
        }

        ExamAttemptAnswer::updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'exam_attempt_question_id' => $attemptQuestion->id],
            ['selected_answer_ids' => array_values($answerIds)]
        );

        // Autosave is the most frequent round trip on the page, so it carries
        // the authoritative clock along for free.
        return response()->json([
            'success' => true,
            'remaining_seconds' => $attempt->remainingSeconds(),
        ]);
    }

    public function logEvent(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $attempt = ExamAttempt::active()
            ->where('exam_id', $exam->id)
            ->where('user_id', Auth::id())
            ->whereNull('completed_at')
            ->first();

        if (! $attempt) {
            return response()->json(['success' => false, 'message' => 'No active attempt.'], 409);
        }

        // Deliberately not a FormRequest: those validate before the method body
        // runs, and the two 409 guards above have to answer first. The exam page
        // treats 409 as final and stops, but retries anything else three times
        // and then queues it forever - so turning an expired attempt into a 422
        // would leave the client hammering a closed attempt.
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(ExamAttemptEvent::TYPES)],
        ]);

        $attempt->events()->create([
            'type' => $validated['type'],
            'occurred_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function verifyExamPassword(VerifyExamPasswordRequest $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        if (! $exam->requiresEntryPassword() || $request->entry_password !== $exam->entry_password) {
            return back()->withErrors(['entry_password' => 'Incorrect password.']);
        }

        session(["exam_password_verified_{$exam->id}" => true]);

        return redirect()->route('student.exam', $exam->id);
    }

    public function submitExam(Request $request, $examId, ExamScoringService $scoring)
    {
        $exam = Exam::findOrFail($examId);

        $attempt = ExamAttempt::active()
            ->where('exam_id', $exam->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($attempt, 404);

        if ($attempt->completed_at !== null) {
            return redirect()->route('student.exams')->with('error', 'You have already completed this exam.');
        }

        // Determine whether the time limit has actually expired server-side, so a
        // manipulated client can't bypass the "all questions answered" requirement
        // or, worse, replay/edit MCQ answers in the request body after time is up.
        // Once expired, this request's answers.* are ignored entirely below in
        // favor of whatever was already autosaved before the deadline.
        $isExpired = $attempt->isExpired();

        $attemptQuestions = $scoring->questionsFor($attempt);
        $questionsById = $attemptQuestions->map->question->keyBy('id');

        $request->validate($scoring->rulesFor($attemptQuestions));

        $submittedAnswers = $scoring->resolveAnswers(
            $attemptQuestions,
            $request->input('answers', []),
            $isExpired
        );

        $correctAnswers = $scoring->countCorrect($attemptQuestions, $submittedAnswers);

        // For now, score only includes MCQ questions
        // File upload questions will be graded manually by admin
        $score = $correctAnswers;

        // Files land on disk before the transaction opens. A disk write cannot be
        // rolled back, so doing it inside would leave the uploaded file behind
        // anyway; keeping it out means the transaction holds nothing but row
        // writes and stays short. Metadata is read off the upload before the
        // move, while the temp file is still where UploadedFile expects it.
        //
        // Never accepted once the deadline has passed, since there's no autosaved
        // snapshot of a file the way there is for MCQ answers.
        $storedFiles = [];

        if (! $isExpired && $request->has('file_uploads')) {
            foreach ($request->file('file_uploads') as $questionId => $uploadedFile) {
                if ($uploadedFile && $uploadedFile->isValid()) {
                    $extension = $uploadedFile->getClientOriginalExtension();
                    $filename = 'exam_'.$exam->id.'_student_'.Auth::id().'_question_'.$questionId.'_'.time().'.'.$extension;

                    $originalName = $uploadedFile->getClientOriginalName();
                    $fileSize = $uploadedFile->getSize();
                    $mimeType = $uploadedFile->getMimeType();

                    // Private disk (storage/app/private) — student submissions must not
                    // be reachable via a guessable public URL without authentication.
                    $storedFiles[(int) $questionId] = [
                        'file_path' => $uploadedFile->storeAs('exam_submissions', $filename, 'local'),
                        'original_filename' => $originalName,
                        'file_size' => $fileSize,
                        'file_mime_type' => $mimeType,
                    ];
                }
            }
        }

        // Everything that writes a row goes in one transaction, taking the
        // attempt row first. Same reasoning as ExamGenerationService::generate()
        // on the start path: the completed_at check above runs outside any
        // transaction, so a double-click — or the autosave retry logic in
        // exam.blade.php firing twice on a flaky connection — lets both requests
        // read "not completed" and both write a result. Re-reading completed_at
        // under the lock makes the second request wait and then see the first
        // one's work. Without the transaction a request dying midway would also
        // leave a result row behind on an attempt still marked open.
        //
        // Returns null when the attempt was finalized by whoever got here first.
        $examResult = DB::transaction(function () use (
            $attempt, $exam, $attemptQuestions, $questionsById,
            $submittedAnswers, $correctAnswers, $score, $isExpired, $storedFiles
        ) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked === null || $locked->completed_at !== null) {
                return null;
            }

            $examResult = ExamResult::create([
                'exam_id' => $exam->id,
                'exam_attempt_id' => $attempt->id,
                'user_id' => Auth::id(),
                'total_questions' => $attemptQuestions->count(),
                'correct_answers' => $correctAnswers,
                'score' => $score,
                'submitted_at' => now(),
            ]);

            // Store MCQ answers
            if ($submittedAnswers->isNotEmpty()) {
                foreach ($submittedAnswers as $questionId => $answerData) {
                    $question = $questionsById->get($questionId);

                    if ($question->question_type === 'single') {
                        // Single choice: answerData is a single answer ID. On an
                        // auto-submit it can be absent - storing a null answer_id
                        // would blow up the results page, which dereferences it.
                        if ($answerData !== null) {
                            StudentAnswer::create([
                                'exam_result_id' => $examResult->id,
                                'question_id' => $questionId,
                                'answer_id' => $answerData,
                            ]);
                        }
                    } else {
                        // Multiple choice: answerData is an array of answer IDs
                        $selectedAnswerIds = is_array($answerData) ? $answerData : [$answerData];

                        // Create a student answer record for each selected option
                        foreach ($selectedAnswerIds as $answerId) {
                            StudentAnswer::create([
                                'exam_result_id' => $examResult->id,
                                'question_id' => $questionId,
                                'answer_id' => $answerId,
                            ]);
                        }
                    }
                }
            }

            foreach ($storedFiles as $questionId => $file) {
                StudentAnswer::create([
                    'exam_result_id' => $examResult->id,
                    'question_id' => $questionId,
                    'answer_id' => null, // No answer_id for file uploads
                    'file_path' => $file['file_path'],
                    'original_filename' => $file['original_filename'],
                    'file_size' => $file['file_size'],
                    'file_mime_type' => $file['file_mime_type'],
                    'is_graded' => false,
                ]);
            }

            // A file-upload question with no file stored - skipped outright, or never
            // reached before time ran out - otherwise has no StudentAnswer row at
            // all, and admin grading has nothing to show or grade for it.
            foreach ($questionsById as $question) {
                if ($question->question_type !== 'file_upload'
                    || array_key_exists($question->id, $storedFiles)) {
                    continue;
                }

                StudentAnswer::create([
                    'exam_result_id' => $examResult->id,
                    'question_id' => $question->id,
                    'answer_id' => null,
                    'file_path' => null,
                    'admin_feedback' => $isExpired
                        ? 'File not submitted before time limit.'
                        : 'File not submitted.',
                    'is_graded' => false,
                ]);
            }

            $locked->update(['completed_at' => now()]);

            // Drafts are no longer needed once the attempt is finalized.
            ExamAttemptAnswer::where('exam_attempt_id', $attempt->id)->delete();

            return $examResult;
        });

        // Clear the exam-in-progress session markers
        session()->forget(['student_exam_id', "exam_password_verified_{$examId}"]);

        if ($examResult === null) {
            // Someone finalised this attempt between storing the uploads above
            // and taking the lock. Those files now belong to no StudentAnswer
            // row, and nothing else will ever come looking for them.
            foreach ($storedFiles as $file) {
                Storage::disk('local')->delete($file['file_path']);
            }

            return redirect()->route('student.exams')->with('error', 'You have already completed this exam.');
        }

        return view('student.result', compact('examResult', 'exam'));
    }

    public function logout(Request $request)
    {
        session()->forget(['student_exam_id']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login')->with('success', 'You have been successfully logged out.');
    }

    public function profile()
    {
        $user = Auth::user();

        return view('student.profile', [
            'user' => $user,
            'finLocked' => $user->finCodeIsLocked(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        // fin_code is absent from validated() whenever it is locked, because
        // UpdateProfileRequest leaves it out of the rules entirely - so a
        // hand-crafted POST carrying it writes nothing.
        Auth::user()->update($request->validated());

        return redirect()->route('student.profile')->with('success', 'Your details have been updated.');
    }

    /**
     * A signed-in student changing their own password.
     *
     * This is deliberately different from the forgotten-password flow, which
     * stays admin-approved: there the student cannot prove who they are, whereas
     * here they are already authenticated and re-type the current password.
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $user->update(['password' => $validated['password']]);

        // Any request they raised before remembering their password would
        // otherwise sit in the admin queue forever.
        PasswordResetRequest::closePendingFor($user);

        $request->session()->regenerate();

        return redirect()->route('student.profile')->with('success', 'Your password has been changed.');
    }

    /**
     * Everything student.partials.result-list renders. Shared with
     * myResultsState() for the same reason as examListData().
     */
    private function resultListData(): array
    {
        $examResults = ExamResult::where('user_id', Auth::id())
            ->with(['exam', 'studentAnswers'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        return compact('examResults');
    }

    public function myResults()
    {
        return view('student.my-results', $this->resultListData());
    }

    /**
     * The results list as the student's open page should now see it - chiefly so
     * a score replaces "Grading Pending" as soon as an admin marks a file answer.
     */
    public function myResultsState(Request $request)
    {
        return $this->fragmentState($request, 'student.partials.result-list', $this->resultListData());
    }

    /**
     * A student's own summary for one sitting.
     *
     * Deliberately shows no questions, no chosen answers and no answer key: a
     * student reviewing this page must not be able to read the bank back out of
     * it. Only facts about the sitting itself are exposed.
     *
     * That also removes the reason the page used to gate itself on an open
     * retake — with no answer key on the page there is nothing left to leak
     * into an attempt that is still running.
     */
    public function showResult(ExamResult $examResult)
    {
        abort_unless($examResult->user_id === Auth::id(), 403);

        // studentAnswers is loaded for hasGradingPending(), which reads only
        // file_path/is_graded off those rows — questions and answers stay unloaded.
        $examResult->load(['exam', 'user', 'examAttempt', 'studentAnswers']);

        $attempt = $examResult->examAttempt;

        // The attempt is SET NULL when an admin clears history, so a result can
        // outlive it; duration is simply unavailable in that case.
        // Carbon 3 returns a float here, which would render as "30.41666 min".
        $durationMinutes = ($attempt && $attempt->started_at && $attempt->completed_at)
            ? (int) round($attempt->started_at->diffInMinutes($attempt->completed_at))
            : null;

        return view('student.check-results-display', [
            'examResult' => $examResult,
            'exam' => $examResult->exam,
            'gradingPending' => $examResult->hasGradingPending(),
            'durationMinutes' => $durationMinutes,
        ]);
    }
}
