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

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestionBank;
use App\Models\ExamResult;
use App\Models\PasswordResetRequest;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\AdminCredentials;
use App\Services\QuestionImportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminController extends Controller
{
    public function __construct(private readonly AdminCredentials $credentials) {}

    public function login()
    {
        return view('admin.login');
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! $this->credentials->verifyLogin($request->username, $request->password)) {
            return back()->with('error', 'Invalid username or password.');
        }

        $request->session()->regenerate();
        session(['admin_logged_in' => true]);

        return redirect()->route('admin.dashboard')->with('success', 'Welcome to Admin Dashboard!');
    }

    public function logout()
    {
        session()->forget('admin_logged_in');

        return redirect()->route('admin.login')->with('success', 'You have been logged out successfully.');
    }

    /**
     * Re-check the admin password for destructive actions.
     *
     * Being logged in is not enough to delete a bank or an exam — an unattended
     * open session should not be one click away from wiping content. The actual
     * comparison lives in AdminCredentials so this gate and the login check can
     * never disagree about which password is current.
     */
    private function adminPasswordMatches(?string $candidate): bool
    {
        return $this->credentials->passwordMatches($candidate);
    }

    /**
     * The credential settings page.
     */
    public function settings()
    {
        return view('admin.settings', [
            'username' => $this->credentials->username(),
            'usingEnvFallback' => $this->credentials->isUsingEnvFallback(),
        ]);
    }

    public function updateCredentials(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'username' => ['required', 'string', 'max:255'],
            // Nullable so the username can be corrected on its own. Longer than
            // the students' min:8 because this one credential authorises every
            // destructive action in the system.
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        if (! $this->credentials->passwordMatches($validated['current_password'])) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $this->credentials->update($validated['username'], $validated['password'] ?? null);

        // Keep the admin signed in, but on a fresh session id.
        $request->session()->regenerate();
        session(['admin_logged_in' => true]);

        $message = filled($validated['password'] ?? null)
            ? 'Admin credentials updated. Your new password is now required everywhere, including delete confirmations.'
            : 'Admin username updated.';

        return redirect()->route('admin.settings')->with('success', $message);
    }

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

    public function createExam()
    {
        return view('admin.create-exam');
    }

    public function storeExam(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|string|unique:exams,exam_id',
            'exam_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'entry_password' => 'nullable|string|max:255',
        ]);

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

    public function updateExam(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $request->validate([
            'exam_id' => 'required|string|unique:exams,exam_id,'.$exam->id,
            'exam_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'entry_password' => 'nullable|string|max:255',
        ]);

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

    public function storeBank(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $bank = QuestionBank::create($request->only(['name', 'description']));

        return redirect()->route('admin.bank-questions', $bank->id)
            ->with('success', 'Question bank created successfully!');
    }

    public function editBank($bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);

        return view('admin.edit-bank', compact('bank'));
    }

    public function updateBank(Request $request, $bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

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

        if (! $this->adminPasswordMatches($request->input('admin_password'))) {
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

    public function bankQuestions($bankId)
    {
        $bank = QuestionBank::findOrFail($bankId);
        $questions = $bank->questions()->with('answers')->get();

        return view('admin.bank-questions', compact('bank', 'questions'));
    }

    public function storeQuestion(Request $request, $bankId)
    {
        // Base validation rules
        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:single,multiple,file_upload',
            'difficulty' => 'required|in:easy,medium,hard',
        ];

        // Add type-specific validation rules
        if ($request->question_type === 'file_upload') {
            $rules += [
                'file_upload_settings.allowed_extensions' => 'nullable|array',
                'file_upload_settings.allowed_extensions.*' => 'string|in:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt',
                'file_upload_settings.max_size_mb' => 'nullable|integer|min:1|max:100',
            ];
        } else {
            $rules += [
                'answers' => 'required|array|min:2',
                'answers.*' => 'required|string',
                'correct_answers' => 'required|array|min:1',
                'correct_answers.*' => 'required|integer',
            ];

            // A single-choice question with two correct answers is unscoreable.
            // Only the create form's JS enforced this before, so anything that
            // did not come through that form could store one.
            if ($request->question_type === 'single') {
                $rules['correct_answers'] = 'required|array|size:1';
            }
        }

        // Filter out empty answers before validation
        if ($request->has('answers')) {
            // Captured BEFORE the merge below: correct_answers holds positions in
            // the form's array, so remapping them needs the array as submitted.
            // Reading it back after the merge compares form positions against the
            // already-filtered list and silently marks the wrong option correct.
            $originalAnswers = $request->input('answers');

            $filteredAnswers = array_filter($request->answers, function ($answer) {
                return ! empty(trim($answer));
            });
            $filteredAnswers = array_values($filteredAnswers); // Re-index array
            $request->merge(['answers' => $filteredAnswers]);

            // Adjust correct_answers indices to match filtered answers
            if ($request->has('correct_answers')) {
                $correctAnswersAdjusted = [];

                foreach ($request->correct_answers as $originalIndex) {
                    // Find the new index in filtered array
                    $newIndex = 0;
                    $currentIndex = 0;

                    foreach ($originalAnswers as $idx => $answer) {
                        if (! empty(trim($answer))) {
                            if ($idx == $originalIndex) {
                                $correctAnswersAdjusted[] = $newIndex;
                                break;
                            }
                            $newIndex++;
                        }
                    }
                }

                $request->merge(['correct_answers' => $correctAnswersAdjusted]);
            }
        }

        $request->validate($rules);

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
                    if (! empty(trim($answerText))) {
                        Answer::create([
                            'question_id' => $question->id,
                            'answer_text' => $answerText,
                            'is_correct' => in_array($index, $request->correct_answers),
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.bank-questions', $bankId)->with('success', 'Question added successfully!');
    }

    /**
     * The bulk-import page for one bank.
     *
     * Deliberately not sharing a layer with storeQuestion above: that method
     * takes a wide flat form payload, branches into a file_upload arm, filters
     * blank answers and re-maps correct_answers against the filtered array —
     * none of which an import has or wants. A common abstraction over the two
     * shapes would be more branches than the handful of lines it saves.
     */
    public function importQuestions($bankId)
    {
        $bank = QuestionBank::withCount('questions')->findOrFail($bankId);

        return view('admin.import-questions', compact('bank'));
    }

    public function storeImportedQuestions(Request $request, $bankId, QuestionImportService $importer)
    {
        $bank = QuestionBank::findOrFail($bankId);

        $request->validate([
            // Extension-only on purpose. MIME sniffing for CSV/JSON is unreliable
            // in practice (finfo says text/plain for CSV, Excel uploads arrive as
            // application/vnd.ms-excel, some browsers send octet-stream for .json)
            // and would reject legitimate files while buying no safety here — the
            // parser reads text and never evaluates it.
            'file' => ['required', 'file', 'max:2048', 'extensions:csv,txt,json'],
        ]);

        $file = $request->file('file');
        $format = strtolower($file->getClientOriginalExtension()) === 'json' ? 'json' : 'csv';

        // Read straight from PHP's upload temp path — never ->store(). The only
        // persistent volume in production is storage_data, and spooled imports
        // would accumulate there with nothing to clean them up.
        $contents = (string) $file->get();

        if (trim($contents) === '') {
            return back()->withErrors(['file' => 'The uploaded file is empty.']);
        }

        $result = $importer->parse(
            $contents,
            $format,
            Question::where('question_bank_id', $bank->id)->pluck('question_text')->all(),
            $request->boolean('skip_duplicates')
        );

        if ($result['errors']->isNotEmpty()) {
            return back()->withErrors($result['errors'])->withInput();
        }

        $created = $importer->import($bank, $result['rows']);

        $message = "Imported {$created} question(s) into \"{$bank->name}\".";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} already in this bank.";
        }

        return redirect()->route('admin.bank-questions', $bank->id)->with('success', $message);
    }

    public function importTemplate($format, QuestionImportService $importer)
    {
        if ($format === 'json') {
            return response($importer->jsonTemplate(), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="question-import-template.json"',
            ]);
        }

        // The BOM is what makes Excel open non-ASCII sample text correctly.
        return response("\xEF\xBB\xBF".$importer->csvTemplate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="question-import-template.csv"',
        ]);
    }

    public function editQuestion($questionId)
    {
        $question = Question::with(['answers', 'questionBank'])->findOrFail($questionId);

        return view('admin.edit-question', compact('question'));
    }

    public function updateQuestion(Request $request, $questionId)
    {
        // Base validation rules
        $rules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:single,multiple,file_upload',
            'difficulty' => 'required|in:easy,medium,hard',
        ];

        // Add type-specific validation rules
        if ($request->question_type === 'file_upload') {
            $rules += [
                'file_upload_settings.allowed_extensions' => 'nullable|array',
                'file_upload_settings.allowed_extensions.*' => 'string|in:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt',
                'file_upload_settings.max_size_mb' => 'nullable|integer|min:1|max:100',
            ];
        } else {
            $rules += [
                'answers' => 'required|array|min:2',
                'answers.*' => 'nullable|string',
                'correct_answers' => 'required|array|min:1',
                'correct_answers.*' => 'required|integer',
            ];

            // Same rule as storeQuestion: editing must not be a way to reach the
            // unscoreable "single choice with two correct answers" state that
            // creating one already refuses.
            if ($request->question_type === 'single') {
                $rules['correct_answers'] = 'required|array|size:1';
            }
        }

        // Filter out empty answers before validation (same logic as storeQuestion)
        if ($request->has('answers')) {
            // Captured BEFORE the merge below: correct_answers holds positions in
            // the form's array, so remapping them needs the array as submitted.
            // Reading it back after the merge compares form positions against the
            // already-filtered list and silently marks the wrong option correct.
            $originalAnswers = $request->input('answers');

            $filteredAnswers = array_filter($request->answers, function ($answer) {
                return ! empty(trim($answer));
            });
            $filteredAnswers = array_values($filteredAnswers); // Re-index array
            $request->merge(['answers' => $filteredAnswers]);

            // Adjust correct_answers indices to match filtered answers
            if ($request->has('correct_answers')) {
                $correctAnswersAdjusted = [];

                foreach ($request->correct_answers as $originalIndex) {
                    // Find the new index in filtered array
                    $newIndex = 0;
                    $currentIndex = 0;

                    foreach ($originalAnswers as $idx => $answer) {
                        if (! empty(trim($answer))) {
                            if ($idx == $originalIndex) {
                                $correctAnswersAdjusted[] = $newIndex;
                                break;
                            }
                            $newIndex++;
                        }
                    }
                }

                $request->merge(['correct_answers' => $correctAnswersAdjusted]);
            }
        }

        $request->validate($rules);

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
            $submitted = array_values(array_filter(
                $request->answers,
                fn ($answerText) => trim((string) $answerText) !== ''
            ));

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

    public function gradeFileSubmission(Request $request, $studentAnswerId)
    {
        $request->validate([
            'manual_score' => 'required|numeric|min:0|max:100',
            'admin_feedback' => 'nullable|string|max:1000',
        ]);

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

        if (! $this->adminPasswordMatches($request->input('admin_password'))) {
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
            // Same race as deleteBank: the attempt/result guards are read before
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

    public function attachBank(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $request->validate([
            'question_bank_id' => 'required|exists:question_banks,id',
            'quota_easy' => 'required|integer|min:0',
            'quota_medium' => 'required|integer|min:0',
            'quota_hard' => 'required|integer|min:0',
        ]);

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

    public function updateBankQuota(Request $request, $examId, $bankAssignmentId)
    {
        $eqb = ExamQuestionBank::where('exam_id', $examId)->findOrFail($bankAssignmentId);

        $request->validate([
            'quota_easy' => 'required|integer|min:0',
            'quota_medium' => 'required|integer|min:0',
            'quota_hard' => 'required|integer|min:0',
        ]);

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
        try {
            $exam = Exam::findOrFail($examId);

            // Get all results for this exam
            $results = ExamResult::where('exam_id', $exam->id)
                ->with('user', 'studentAnswers.question', 'studentAnswers.answer')
                ->orderBy('submitted_at', 'desc')
                ->get();

            if ($results->isEmpty()) {
                return redirect()->back()->with('error', 'No results found for this exam.');
            }

            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Disable automatic calculation to prevent formula issues
            $spreadsheet->getCalculationEngine()->disableCalculationCache();
            Calculation::getInstance($spreadsheet)->setCalculationCacheEnabled(false);

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('SITC Exam System')
                ->setTitle($exam->exam_name.' - Results')
                ->setSubject('Exam Results')
                ->setDescription('Detailed exam results for '.$exam->exam_name);

            // Sheet title
            $sheet->setTitle('Exam Results');

            // Header styling
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];

            // Exam info section
            $this->setSafeStringValue($sheet, 'A1', 'Exam Name:');
            $this->setSafeStringValue($sheet, 'B1', $exam->exam_name);
            $this->setSafeStringValue($sheet, 'A2', 'Exam ID:');
            $this->setSafeStringValue($sheet, 'B2', $exam->exam_id);
            $this->setSafeStringValue($sheet, 'A3', 'Total Submissions:');
            $sheet->setCellValue('B3', $results->count());
            $this->setSafeStringValue($sheet, 'A4', 'Average Score:');
            $sheet->setCellValue('B4', round($results->avg('score'), 2));
            $this->setSafeStringValue($sheet, 'A5', 'Generated On:');
            $this->setSafeStringValue($sheet, 'B5', now()->format('Y-m-d H:i:s'));

            // Style exam info
            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            $sheet->getStyle('A1:B5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Summary header row (starting from row 7)
            $summaryRow = 7;
            $headers = [
                'A' => 'Full Name',
                'B' => 'FIN Code',
                'C' => 'Score',
                'D' => 'Correct Answers',
                'E' => 'Total Questions',
                'F' => 'Percentage',
                'G' => 'Submitted At',
            ];

            foreach ($headers as $col => $header) {
                $this->setSafeStringValue($sheet, $col.$summaryRow, $header);
            }

            $sheet->getStyle('A'.$summaryRow.':G'.$summaryRow)->applyFromArray($headerStyle);

            // Fill summary data
            $row = $summaryRow + 1;
            foreach ($results as $result) {
                $percentage = $result->total_questions > 0 ? round(($result->correct_answers / $result->total_questions) * 100, 1) : 0;

                $this->setSafeStringValue($sheet, 'A'.$row, $result->user?->name ?? 'N/A');
                $this->setSafeStringValue($sheet, 'B'.$row, $result->user?->fin_code ?? 'N/A');
                $sheet->setCellValue('C'.$row, $result->score);
                $sheet->setCellValue('D'.$row, $result->correct_answers);
                $sheet->setCellValue('E'.$row, $result->total_questions);
                $this->setSafeStringValue($sheet, 'F'.$row, $percentage.'%');
                $this->setSafeStringValue($sheet, 'G'.$row, $result->submitted_at->format('Y-m-d H:i:s'));

                // Style data rows
                $sheet->getStyle('A'.$row.':G'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                if ($row % 2 == 0) {
                    $sheet->getStyle('A'.$row.':G'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                }

                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Add detailed answers sheet
            $detailSheet = $spreadsheet->createSheet(1);
            $detailSheet->setTitle('Detailed Answers');

            $detailRow = 1;

            // Detailed header
            $detailHeaders = [
                'A' => 'Full Name',
                'B' => 'FIN Code',
                'C' => 'Question #',
                'D' => 'Question Text',
                'E' => 'Question Type',
                'F' => 'Student Answer',
                'G' => 'Correct Answer',
                'H' => 'Is Correct',
                'I' => 'File Submission',
                'J' => 'File Size',
                'K' => 'Grading Status',
                'L' => 'Graded Score',
                'M' => 'Grading Notes',
            ];

            foreach ($detailHeaders as $col => $header) {
                $this->setSafeStringValue($detailSheet, $col.$detailRow, $header);
            }

            $detailSheet->getStyle('A'.$detailRow.':M'.$detailRow)->applyFromArray($headerStyle);

            $detailRow++;

            // Fill detailed data
            foreach ($results as $result) {
                foreach ($result->studentAnswers as $index => $studentAnswer) {
                    $question = $studentAnswer->question;
                    $isFileUpload = $question->isFileUpload();

                    $this->setSafeStringValue($detailSheet, 'A'.$detailRow, $result->user?->name ?? 'N/A');
                    $this->setSafeStringValue($detailSheet, 'B'.$detailRow, $result->user?->fin_code ?? 'N/A');
                    $detailSheet->setCellValue('C'.$detailRow, $index + 1);
                    $this->setSafeStringValue($detailSheet, 'D'.$detailRow, $question->question_text);
                    $this->setSafeStringValue($detailSheet, 'E'.$detailRow, $isFileUpload ? 'File Upload' : 'Multiple Choice');

                    if ($isFileUpload) {
                        // Handle file upload questions
                        $this->setSafeStringValue($detailSheet, 'F'.$detailRow, 'File Uploaded');
                        $this->setSafeStringValue($detailSheet, 'G'.$detailRow, 'Manual Grading Required');
                        $this->setSafeStringValue($detailSheet, 'H'.$detailRow, $studentAnswer->is_graded ?
                            ($studentAnswer->manual_score >= 50 ? 'Passed' : 'Failed') : 'Pending');

                        // File submission details
                        if ($studentAnswer->file_path) {
                            $fileUrl = url('storage/'.$studentAnswer->file_path);
                            $this->setSafeStringValue($detailSheet, 'I'.$detailRow, $fileUrl);
                            $this->setSafeStringValue($detailSheet, 'J'.$detailRow, $studentAnswer->getFormattedFileSize());
                        } else {
                            $this->setSafeStringValue($detailSheet, 'I'.$detailRow, 'No file submitted');
                            $this->setSafeStringValue($detailSheet, 'J'.$detailRow, '');
                        }

                        $this->setSafeStringValue($detailSheet, 'K'.$detailRow,
                            $studentAnswer->is_graded ? 'Graded' : 'Pending');
                        $this->setSafeStringValue($detailSheet, 'L'.$detailRow,
                            $studentAnswer->manual_score !== null ? $studentAnswer->manual_score : '');
                        $this->setSafeStringValue($detailSheet, 'M'.$detailRow,
                            $studentAnswer->admin_feedback ?? '');
                    } else {
                        // Handle MCQ questions
                        $correctAnswer = $question->answers->where('is_correct', true)->first();
                        $isCorrect = $studentAnswer->answer && $studentAnswer->answer->is_correct;

                        $this->setSafeStringValue($detailSheet, 'F'.$detailRow,
                            $studentAnswer->answer ? $studentAnswer->answer->answer_text : 'No answer');
                        $this->setSafeStringValue($detailSheet, 'G'.$detailRow,
                            $correctAnswer ? $correctAnswer->answer_text : 'N/A');
                        $this->setSafeStringValue($detailSheet, 'H'.$detailRow,
                            $isCorrect ? 'Correct' : 'Incorrect');

                        // Empty file-related columns for MCQ
                        $this->setSafeStringValue($detailSheet, 'I'.$detailRow, '');
                        $this->setSafeStringValue($detailSheet, 'J'.$detailRow, '');
                        $this->setSafeStringValue($detailSheet, 'K'.$detailRow, 'Auto-graded');
                        $this->setSafeStringValue($detailSheet, 'L'.$detailRow, $studentAnswer->is_correct ? '1' : '0');
                        $this->setSafeStringValue($detailSheet, 'M'.$detailRow, '');
                    }

                    // Style detail rows
                    $detailSheet->getStyle('A'.$detailRow.':M'.$detailRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    // Color code correct/incorrect answers
                    if ($isFileUpload) {
                        if ($studentAnswer->is_graded) {
                            if ($studentAnswer->is_correct) {
                                $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');
                            } else {
                                $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                            }
                        } else {
                            $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C');
                        }
                    } else {
                        if ($studentAnswer->is_correct) {
                            $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');
                        } else {
                            $detailSheet->getStyle('H'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                        }
                    }

                    if ($detailRow % 2 == 0) {
                        $detailSheet->getStyle('A'.$detailRow.':M'.$detailRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9F9F9');
                    }

                    $detailRow++;
                }
            }

            // Auto-size columns for detail sheet
            foreach (range('A', 'M') as $column) {
                $detailSheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Generate filename
            $filename = 'exam_results_'.$exam->exam_id.'_'.now()->format('Y-m-d_H-i-s').'.xlsx';

            // Create writer and save to output
            $writer = new Xlsx($spreadsheet);

            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');

            // Save to php://output
            $writer->save('php://output');

            exit;

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Excel download error for exam ID '.$examId.': '.$e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while generating the Excel file. Please try again.');
        }
    }

    private function setSafeStringValue($sheet, $cell, $value)
    {
        // Handle null or empty values
        if ($value === null) {
            $value = '';
        }

        // Convert value to string and escape any potential formula characters
        $safeValue = (string) $value;

        // Trim whitespace
        $safeValue = trim($safeValue);

        // If the value starts with =, +, -, @ or has formula-like content, prepend with single quote
        if (preg_match('/^[=+\-@]/', $safeValue) || strpos($safeValue, '=') !== false) {
            $safeValue = "'".$safeValue;
        }

        // Remove or escape any problematic characters that might cause issues
        $safeValue = str_replace(["\r\n", "\r", "\n"], ' ', $safeValue); // Replace line breaks with spaces

        $sheet->setCellValueExplicit($cell, $safeValue, DataType::TYPE_STRING);
    }

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

    public function updateStudent(Request $request, $userId)
    {
        $student = User::findOrFail($userId);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
            'fin_code' => ['required', 'string', 'max:20', Rule::unique('users', 'fin_code')->ignore($student->id)],
        ]);

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

    /**
     * Set a student's password directly, either to something the admin typed or
     * to the student's own FIN code. Either way this also closes any reset
     * request the student had open - the request has been answered, so leaving
     * it pending would just make the admin handle it twice.
     */
    public function setStudentPassword(Request $request, $userId)
    {
        $student = User::findOrFail($userId);

        if ($request->input('mode') === 'fin') {
            $student->update(['password' => $student->fin_code]);
            PasswordResetRequest::closePendingFor($student);

            return back()->with('success', "Password for {$student->name} is now their FIN code ({$student->fin_code}).");
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $student->update(['password' => $validated['password']]);
        PasswordResetRequest::closePendingFor($student);

        // The admin chose this password, so there is nothing to hand back - and
        // echoing it into a flash message would put it in the session store.
        return back()->with('success', "Password updated for {$student->name}.");
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
