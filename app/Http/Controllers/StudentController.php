<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Controllers;

use App\Exceptions\ConcurrentExamAttemptException;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptEvent;
use App\Models\ExamResult;
use App\Models\PasswordResetRequest;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\ExamGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function register()
    {
        return view('student.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users,email',
            'fin_code' => 'required|string|max:20|unique:users,fin_code',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'email' => $validated['email'],
            'fin_code' => $validated['fin_code'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('student.login')->with('success', 'Account created successfully! Please sign in.');
    }

    public function login()
    {
        return view('student.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('student.exams');
    }

    public function passwordRequest()
    {
        return view('student.password-request');
    }

    /**
     * Raise a password reset request for an admin to approve. Students never
     * reset their own password here - approval hands them a generated one.
     */
    public function storePasswordRequest(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

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

    public function exams()
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

        return view('student.exams', compact('activeExams', 'openAttempt'));
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

        if (!$exam->is_active) {
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

        if (!$attempt) {
            if ($exam->requiresEntryPassword() && !session()->get("exam_password_verified_{$exam->id}")) {
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
            } catch (\RuntimeException $e) {
                \Log::error('Exam generation failed for exam ID ' . $exam->id . ': ' . $e->getMessage());
                return redirect()->route('student.exams')->with('error', 'This exam could not be started right now. Please contact your administrator.');
            }
        }

        $remainingSeconds = $attempt->expires_at
            ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false))
            : null;

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
     */
    public function keepAlive()
    {
        return response()->json([
            'ok' => true,
            'token' => csrf_token(),
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

        if (!$attempt) {
            return response()->json(['success' => false, 'message' => 'No active attempt.'], 409);
        }

        if ($attempt->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Time limit reached.'], 409);
        }

        $validated = $request->validate([
            'question_id' => 'required|integer',
            'answer_indexes' => 'nullable|array',
            'answer_indexes.*' => 'integer|min:0',
        ]);

        $attemptQuestion = $attempt->attemptQuestions()
            ->where('question_id', $validated['question_id'])
            ->first();

        if (!$attemptQuestion) {
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

            return response()->json(['success' => true, 'cleared' => true]);
        }

        ExamAttemptAnswer::updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'exam_attempt_question_id' => $attemptQuestion->id],
            ['selected_answer_ids' => array_values($answerIds)]
        );

        return response()->json(['success' => true]);
    }

    public function logEvent(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $attempt = ExamAttempt::active()
            ->where('exam_id', $exam->id)
            ->where('user_id', Auth::id())
            ->whereNull('completed_at')
            ->first();

        if (!$attempt) {
            return response()->json(['success' => false, 'message' => 'No active attempt.'], 409);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(ExamAttemptEvent::TYPES)],
        ]);

        $attempt->events()->create([
            'type' => $validated['type'],
            'occurred_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function verifyExamPassword(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $request->validate([
            'entry_password' => 'required|string',
        ]);

        if (!$exam->requiresEntryPassword() || $request->entry_password !== $exam->entry_password) {
            return back()->withErrors(['entry_password' => 'Incorrect password.']);
        }

        session(["exam_password_verified_{$exam->id}" => true]);

        return redirect()->route('student.exam', $exam->id);
    }

    public function submitExam(Request $request, $examId)
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

        $attemptQuestions = $attempt->attemptQuestions()->with(['question.answers', 'draftAnswer'])->get();
        $questionsById = $attemptQuestions->map->question->keyBy('id');
        $attemptQuestionsByQuestionId = $attemptQuestions->keyBy('question_id');

        // Build validation rules dynamically based on question types
        $rules = [];
        $mcqQuestions = 0;
        $fileUploadQuestions = 0;

        foreach ($questionsById as $question) {
            if ($question->question_type === 'file_upload') {
                $fileUploadQuestions++;
                $allowedExtensions = implode(',', $question->getAllowedExtensions());
                $maxSizeMb = $question->getMaxFileSize();

                $fileRule = $isExpired ? 'nullable' : 'required';
                $rules["file_uploads.{$question->id}"] = "{$fileRule}|file|mimes:{$allowedExtensions}|max:" . ($maxSizeMb * 1024);
            } else {
                // Answers arrive as positions in this attempt's shuffled option
                // order, so the bound is the option count - not an answers.id.
                $lastPosition = max(0, $attemptQuestionsByQuestionId[$question->id]
                    ->orderedAnswers()->count() - 1);

                if ($question->question_type === 'single') {
                    $mcqQuestions++;
                    $answerRule = $isExpired ? 'nullable' : 'required';
                    $rules["answers.{$question->id}"] = "{$answerRule}|integer|min:0|max:{$lastPosition}";
                } else { // multiple choice
                    $mcqQuestions++;
                    $answerRule = $isExpired ? 'nullable|array' : 'required|array|min:1';
                    $rules["answers.{$question->id}"] = $answerRule;
                    $rules["answers.{$question->id}.*"] = "integer|min:0|max:{$lastPosition}";
                }
            }
        }

        $request->validate($rules);

        // Calculate score for MCQ questions only
        $correctAnswers = 0;
        $totalMcqQuestions = $mcqQuestions;

        if ($isExpired) {
            // The deadline has passed - the only answers that count are whatever
            // was already autosaved (autosaveAnswer() itself now refuses writes
            // once expired, so this is a frozen snapshot from before the
            // deadline). selected_answer_ids already holds real answer ids, not
            // positions, so no position resolution is needed here.
            $submittedAnswers = $attemptQuestions
                ->filter(fn ($aq) => $aq->question->question_type !== 'file_upload')
                ->mapWithKeys(function ($aq) {
                    $ids = $aq->draftAnswer->selected_answer_ids ?? [];
                    $value = $aq->question->question_type === 'single' ? ($ids[0] ?? null) : $ids;

                    return [$aq->question_id => $value];
                });
        } else {
            // Only answers for questions actually served in this attempt count. A
            // question id the student was never given used to dereference null here
            // and 500 before any scoring ran.
            //
            // Each value is a position in that question's pinned answer_display_order;
            // resolve it back to a real answer id here so everything downstream keeps
            // working with ids. Because the position is looked up in its own question's
            // order, one question's answer can never be submitted for another.
            $submittedAnswers = collect($request->input('answers', []))
                ->only($questionsById->keys()->all())
                ->map(function ($positions, $questionId) use ($attemptQuestionsByQuestionId, $questionsById) {
                    $order = $attemptQuestionsByQuestionId[$questionId]->orderedAnswers()->pluck('id');

                    $answerIds = collect(is_array($positions) ? $positions : [$positions])
                        ->unique()
                        ->map(fn ($position) => $order[$position] ?? null)
                        ->filter()
                        ->values();

                    return $questionsById[$questionId]->question_type === 'single'
                        ? $answerIds->first()
                        : $answerIds->all();
                });
        }

        // Process MCQ answers
        if ($submittedAnswers->isNotEmpty()) {
            foreach ($submittedAnswers as $questionId => $answerData) {
                $question = $questionsById->get($questionId);

                if ($question->question_type === 'single') {
                    // Single choice: answerData is a single answer ID. The answer
                    // must belong to the question being scored - without that check
                    // one known-correct id could be replayed across every question.
                    $answer = \App\Models\Answer::find($answerData);
                    if ($answer && $answer->question_id === $question->id && $answer->is_correct) {
                        $correctAnswers++;
                    }
                } else {
                    // Multiple choice: answerData is an array of answer IDs
                    // Check if selected answers match exactly with correct answers
                    $selectedAnswerIds = is_array($answerData) ? $answerData : [$answerData];
                    $correctAnswerIds = $question->answers()->where('is_correct', true)->pluck('id')->toArray();

                    // For multiple choice, student gets point only if they select ALL correct answers and NO incorrect ones
                    sort($selectedAnswerIds);
                    sort($correctAnswerIds);

                    if ($selectedAnswerIds === $correctAnswerIds) {
                        $correctAnswers++;
                    }
                }
            }
        }

        // For now, score only includes MCQ questions
        // File upload questions will be graded manually by admin
        $score = $correctAnswers;

        // Create exam result
        $examResult = ExamResult::create([
            'exam_id' => $exam->id,
            'exam_attempt_id' => $attempt->id,
            'user_id' => Auth::id(),
            'total_questions' => $attemptQuestions->count(),
            'correct_answers' => $correctAnswers,
            'score' => $score,
            'submitted_at' => now()
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
                            'answer_id' => $answerData
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
                            'answer_id' => $answerId
                        ]);
                    }
                }
            }
        }

        // Store file uploads - never accepted once the deadline has passed, since
        // there's no autosaved snapshot of a file the way there is for MCQ answers.
        if (!$isExpired && $request->has('file_uploads')) {
            foreach ($request->file('file_uploads') as $questionId => $uploadedFile) {
                if ($uploadedFile && $uploadedFile->isValid()) {
                    // Generate unique filename
                    $extension = $uploadedFile->getClientOriginalExtension();
                    $filename = 'exam_' . $exam->id . '_student_' . Auth::id() . '_question_' . $questionId . '_' . time() . '.' . $extension;
                    
                    // Private disk (storage/app/private) — student submissions must not
                    // be reachable via a guessable public URL without authentication.
                    $filePath = $uploadedFile->storeAs('exam_submissions', $filename, 'local');
                    
                    // Create student answer record for file upload
                    StudentAnswer::create([
                        'exam_result_id' => $examResult->id,
                        'question_id' => $questionId,
                        'answer_id' => null, // No answer_id for file uploads
                        'file_path' => $filePath,
                        'original_filename' => $uploadedFile->getClientOriginalName(),
                        'file_size' => $uploadedFile->getSize(),
                        'file_mime_type' => $uploadedFile->getMimeType(),
                        'is_graded' => false
                    ]);
                }
            }
        }

        if ($isExpired) {
            // A file-upload question the student never got to before time ran out
            // otherwise has no StudentAnswer row at all, and admin grading has
            // nothing to show or grade for it.
            foreach ($questionsById as $question) {
                if ($question->question_type !== 'file_upload') {
                    continue;
                }

                StudentAnswer::create([
                    'exam_result_id' => $examResult->id,
                    'question_id' => $question->id,
                    'answer_id' => null,
                    'file_path' => null,
                    'admin_feedback' => 'File not submitted before time limit.',
                    'is_graded' => false,
                ]);
            }
        }

        $attempt->update(['completed_at' => now()]);

        // Drafts are no longer needed once the attempt is finalized.
        ExamAttemptAnswer::where('exam_attempt_id', $attempt->id)->delete();

        // Clear the exam-in-progress session markers
        session()->forget(['student_exam_id', "exam_password_verified_{$examId}"]);

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

    public function myResults()
    {
        $examResults = ExamResult::where('user_id', Auth::id())
            ->with(['exam', 'studentAnswers'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        return view('student.my-results', compact('examResults'));
    }

    public function showResult(ExamResult $examResult)
    {
        abort_unless($examResult->user_id === Auth::id(), 403);

        $examResult->load(['exam', 'user', 'studentAnswers.question', 'studentAnswers.answer']);

        // Check if there are any file upload questions that are not graded yet
        $fileUploadAnswers = $examResult->studentAnswers->filter(function($answer) {
            return $answer->question->isFileUpload();
        });

        $ungradedFiles = $fileUploadAnswers->filter(function($answer) {
            return !$answer->is_graded;
        });

        $hasUngradedFiles = $ungradedFiles->isNotEmpty();
        $exam = $examResult->exam;

        // A granted retake supersedes the old attempt but leaves this page
        // reachable, and it spells out every correct answer. While a fresh
        // attempt is open the student may see their score but not the key.
        $hasOpenAttempt = ExamAttempt::active()
            ->where('exam_id', $examResult->exam_id)
            ->where('user_id', Auth::id())
            ->whereNull('completed_at')
            ->exists();

        return view('student.check-results-display', compact('examResult', 'exam', 'hasUngradedFiles', 'fileUploadAnswers', 'hasOpenAttempt'));
    }
}
