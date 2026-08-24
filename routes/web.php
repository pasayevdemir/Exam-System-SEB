<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\BankQuestionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamBankController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuestionImportController;
use App\Http\Controllers\StudentAdminController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubmissionGradingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/start-exam');

// Admin Authentication Routes (no middleware)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
    // Throttled: the admin password is now a hashed credential at a stable URL,
    // i.e. a worthwhile brute-force target. Same idiom as student.password-request.store.
    Route::post('/authenticate', [AdminAuthController::class, 'authenticate'])
        ->middleware('throttle:5,1')
        ->name('authenticate');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

// Admin Routes (protected by middleware)
Route::prefix('examadmin')->name('admin.')->middleware('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [AdminAuthController::class, 'settings'])->name('settings');
    Route::put('/settings/credentials', [AdminAuthController::class, 'updateCredentials'])->name('update-credentials');
    Route::get('/create-exam', [ExamController::class, 'createExam'])->name('create-exam');
    Route::post('/store-exam', [ExamController::class, 'storeExam'])->name('store-exam');
    Route::get('/exam/{exam}/edit', [ExamController::class, 'editExam'])->name('edit-exam');
    Route::put('/exam/{exam}', [ExamController::class, 'updateExam'])->name('update-exam');
    Route::delete('/exam/{exam}', [ExamController::class, 'deleteExam'])->name('delete-exam');

    Route::get('/banks', [QuestionBankController::class, 'banks'])->name('banks');
    Route::get('/banks/create', [QuestionBankController::class, 'createBank'])->name('create-bank');
    Route::post('/banks', [QuestionBankController::class, 'storeBank'])->name('store-bank');
    Route::get('/banks/{bank}/edit', [QuestionBankController::class, 'editBank'])->name('edit-bank');
    Route::put('/banks/{bank}', [QuestionBankController::class, 'updateBank'])->name('update-bank');
    Route::delete('/banks/{bank}', [QuestionBankController::class, 'deleteBank'])->name('delete-bank');
    Route::get('/banks/{bank}/questions', [BankQuestionController::class, 'bankQuestions'])->name('bank-questions');
    Route::post('/banks/{bank}/questions', [BankQuestionController::class, 'storeQuestion'])->name('store-question');
    Route::get('/banks/{bank}/questions/import', [QuestionImportController::class, 'importQuestions'])->name('import-questions');
    Route::post('/banks/{bank}/questions/import', [QuestionImportController::class, 'storeImportedQuestions'])->name('store-imported-questions');
    Route::get('/questions/import-template/{format}', [QuestionImportController::class, 'importTemplate'])
        ->whereIn('format', ['csv', 'json'])
        ->name('import-template');

    Route::get('/question/{question}/edit', [BankQuestionController::class, 'editQuestion'])->name('edit-question');
    Route::put('/question/{question}', [BankQuestionController::class, 'updateQuestion'])->name('update-question');
    Route::delete('/question/{question}', [BankQuestionController::class, 'deleteQuestion'])->name('delete-question');
    Route::put('/student-answer/{studentAnswer}/grade', [SubmissionGradingController::class, 'gradeFileSubmission'])->name('grade-file-submission');
    Route::get('/submission/{studentAnswer}/download', [SubmissionGradingController::class, 'downloadSubmission'])->name('download-submission');
    Route::get('/exam/{exam}/grade-submissions', [SubmissionGradingController::class, 'gradeSubmissions'])->name('grade-submissions');
    Route::post('/exam/{exam}/toggle-status', [ExamController::class, 'toggleExamStatus'])->name('toggle-status');
    Route::get('/exam/{exam}/results', [ExamResultController::class, 'examResults'])->name('exam-results');
    Route::get('/exam/{exam}/results/download', [ExamResultController::class, 'downloadResults'])->name('download-results');
    Route::post('/exam-results/{examResult}/allow-retake', [ExamResultController::class, 'allowRetake'])->name('allow-retake');

    Route::get('/students', [StudentAdminController::class, 'students'])->name('students');
    Route::get('/students/{user}/edit', [StudentAdminController::class, 'editStudent'])->name('edit-student');
    Route::put('/students/{user}', [StudentAdminController::class, 'updateStudent'])->name('update-student');
    Route::delete('/students/{user}', [StudentAdminController::class, 'deleteStudent'])->name('delete-student');
    Route::post('/students/{user}/password', [StudentAdminController::class, 'setStudentPassword'])->name('set-student-password');
    Route::post('/password-requests/{passwordResetRequest}/approve', [StudentAdminController::class, 'approveResetRequest'])->name('approve-reset-request');
    Route::post('/password-requests/{passwordResetRequest}/reject', [StudentAdminController::class, 'rejectResetRequest'])->name('reject-reset-request');

    Route::get('/exam/{exam}/banks', [ExamBankController::class, 'examBanks'])->name('exam-banks');
    Route::post('/exam/{exam}/banks', [ExamBankController::class, 'attachBank'])->name('attach-bank');
    Route::put('/exam/{exam}/banks/{bankAssignment}', [ExamBankController::class, 'updateBankQuota'])->name('update-bank-quota');
    Route::delete('/exam/{exam}/banks/{bankAssignment}', [ExamBankController::class, 'detachBank'])->name('detach-bank');
});

// Student Routes
Route::get('/register', [StudentController::class, 'register'])->name('student.register');
Route::post('/register', [StudentController::class, 'store'])->name('student.store');
Route::get('/start-exam', [StudentController::class, 'login'])->name('student.login');
Route::get('/forgot-password', [StudentController::class, 'passwordRequest'])->name('student.password-request');
Route::post('/forgot-password', [StudentController::class, 'storePasswordRequest'])
    ->middleware('throttle:10,1')
    ->name('student.password-request.store');
Route::prefix('student')->name('student.')->group(function () {
    // Throttled for the same reason as the admin login, and more urgently: an
    // admin can set a student's password to their FIN code, which is short and
    // guessable, so an unlimited login endpoint is a real search space.
    Route::post('/authenticate', [StudentController::class, 'authenticate'])
        ->middleware('throttle:10,1')
        ->name('authenticate');
    Route::post('/logout', [StudentController::class, 'logout'])->name('logout');

    Route::middleware('student')->group(function () {
        Route::get('/exams', [StudentController::class, 'exams'])->name('exams');
        Route::get('/profile', [StudentController::class, 'profile'])->name('profile');
        Route::put('/profile', [StudentController::class, 'updateProfile'])->name('update-profile');
        Route::put('/profile/password', [StudentController::class, 'updatePassword'])->name('update-password');
        Route::get('/my-results', [StudentController::class, 'myResults'])->name('my-results');

        // Live-poll endpoints for the two list pages, so an admin activating an
        // exam or grading a submission reaches an open student page without a
        // reload. Deliberately outside the `seb` group below, matching the pages
        // they refresh — a student watching the list is not sitting an exam yet.
        //
        // Declared before /my-results/{examResult}: registered the other way
        // round, `state` binds as an ExamResult id and 404s.
        Route::get('/exams/state', [StudentController::class, 'examsState'])->name('exams-state');
        Route::get('/my-results/state', [StudentController::class, 'myResultsState'])->name('my-results-state');

        Route::get('/my-results/{examResult}', [StudentController::class, 'showResult'])->name('show-result');

        // Actually taking an exam requires Safe Exam Browser -
        // scoped to just these, not the whole student group, so checking an
        // old result from a normal browser at home still works.
        Route::middleware('seb')->group(function () {
            Route::get('/keep-alive', [StudentController::class, 'keepAlive'])->name('keep-alive');
            Route::get('/exam/{exam}', [StudentController::class, 'exam'])->name('exam');
            // Throttled like the two login endpoints: an exam entry password is
            // a short shared secret handed out in a room, so an unlimited POST
            // here is a small enough search space to be worth guessing.
            Route::post('/exam/{exam}/verify-password', [StudentController::class, 'verifyExamPassword'])
                ->middleware('throttle:10,1')
                ->name('verify-exam-password');
            Route::post('/exam/{exam}/autosave', [StudentController::class, 'autosaveAnswer'])->name('autosave-answer');
            Route::post('/exam/{exam}/event', [StudentController::class, 'logEvent'])->name('log-event')->middleware('throttle:60,1');
            Route::post('/exam/{exam}/submit', [StudentController::class, 'submitExam'])->name('submit-exam');
        });
    });
});
