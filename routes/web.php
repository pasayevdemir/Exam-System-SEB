<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StudentController;

Route::redirect('/', '/start-exam');

// Admin Authentication Routes (no middleware)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'login'])->name('login');
    // Throttled: the admin password is now a hashed credential at a stable URL,
    // i.e. a worthwhile brute-force target. Same idiom as student.password-request.store.
    Route::post('/authenticate', [AdminController::class, 'authenticate'])
        ->middleware('throttle:5,1')
        ->name('authenticate');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
});

// Admin Routes (protected by middleware)
Route::prefix('examadmin')->name('admin.')->middleware('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings/credentials', [AdminController::class, 'updateCredentials'])->name('update-credentials');
    Route::get('/create-exam', [AdminController::class, 'createExam'])->name('create-exam');
    Route::post('/store-exam', [AdminController::class, 'storeExam'])->name('store-exam');
    Route::get('/exam/{exam}/edit', [AdminController::class, 'editExam'])->name('edit-exam');
    Route::put('/exam/{exam}', [AdminController::class, 'updateExam'])->name('update-exam');
    Route::delete('/exam/{exam}', [AdminController::class, 'deleteExam'])->name('delete-exam');

    Route::get('/banks', [AdminController::class, 'banks'])->name('banks');
    Route::get('/banks/create', [AdminController::class, 'createBank'])->name('create-bank');
    Route::post('/banks', [AdminController::class, 'storeBank'])->name('store-bank');
    Route::get('/banks/{bank}/edit', [AdminController::class, 'editBank'])->name('edit-bank');
    Route::put('/banks/{bank}', [AdminController::class, 'updateBank'])->name('update-bank');
    Route::delete('/banks/{bank}', [AdminController::class, 'deleteBank'])->name('delete-bank');
    Route::get('/banks/{bank}/questions', [AdminController::class, 'bankQuestions'])->name('bank-questions');
    Route::post('/banks/{bank}/questions', [AdminController::class, 'storeQuestion'])->name('store-question');
    Route::get('/banks/{bank}/questions/import', [AdminController::class, 'importQuestions'])->name('import-questions');
    Route::post('/banks/{bank}/questions/import', [AdminController::class, 'storeImportedQuestions'])->name('store-imported-questions');
    Route::get('/questions/import-template/{format}', [AdminController::class, 'importTemplate'])
        ->whereIn('format', ['csv', 'json'])
        ->name('import-template');

    Route::get('/question/{question}/edit', [AdminController::class, 'editQuestion'])->name('edit-question');
    Route::put('/question/{question}', [AdminController::class, 'updateQuestion'])->name('update-question');
    Route::delete('/question/{question}', [AdminController::class, 'deleteQuestion'])->name('delete-question');
    Route::put('/student-answer/{studentAnswer}/grade', [AdminController::class, 'gradeFileSubmission'])->name('grade-file-submission');
    Route::get('/submission/{studentAnswer}/download', [AdminController::class, 'downloadSubmission'])->name('download-submission');
    Route::get('/exam/{exam}/grade-submissions', [AdminController::class, 'gradeSubmissions'])->name('grade-submissions');
    Route::post('/exam/{exam}/toggle-status', [AdminController::class, 'toggleExamStatus'])->name('toggle-status');
    Route::get('/exam/{exam}/results', [AdminController::class, 'examResults'])->name('exam-results');
    Route::get('/exam/{exam}/results/download', [AdminController::class, 'downloadResults'])->name('download-results');
    Route::post('/exam-results/{examResult}/allow-retake', [AdminController::class, 'allowRetake'])->name('allow-retake');

    Route::get('/students', [AdminController::class, 'students'])->name('students');
    Route::get('/students/{user}/edit', [AdminController::class, 'editStudent'])->name('edit-student');
    Route::put('/students/{user}', [AdminController::class, 'updateStudent'])->name('update-student');
    Route::delete('/students/{user}', [AdminController::class, 'deleteStudent'])->name('delete-student');
    Route::post('/students/{user}/password', [AdminController::class, 'setStudentPassword'])->name('set-student-password');
    Route::post('/password-requests/{passwordResetRequest}/approve', [AdminController::class, 'approveResetRequest'])->name('approve-reset-request');
    Route::post('/password-requests/{passwordResetRequest}/reject', [AdminController::class, 'rejectResetRequest'])->name('reject-reset-request');

    Route::get('/exam/{exam}/banks', [AdminController::class, 'examBanks'])->name('exam-banks');
    Route::post('/exam/{exam}/banks', [AdminController::class, 'attachBank'])->name('attach-bank');
    Route::put('/exam/{exam}/banks/{bankAssignment}', [AdminController::class, 'updateBankQuota'])->name('update-bank-quota');
    Route::delete('/exam/{exam}/banks/{bankAssignment}', [AdminController::class, 'detachBank'])->name('detach-bank');
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
        Route::get('/my-results/{examResult}', [StudentController::class, 'showResult'])->name('show-result');

        // Actually taking an exam requires Safe Exam Browser -
        // scoped to just these, not the whole student group, so checking an
        // old result from a normal browser at home still works.
        Route::middleware('seb')->group(function () {
            Route::get('/keep-alive', [StudentController::class, 'keepAlive'])->name('keep-alive');
            Route::get('/exam/{exam}', [StudentController::class, 'exam'])->name('exam');
            Route::post('/exam/{exam}/verify-password', [StudentController::class, 'verifyExamPassword'])->name('verify-exam-password');
            Route::post('/exam/{exam}/autosave', [StudentController::class, 'autosaveAnswer'])->name('autosave-answer');
            Route::post('/exam/{exam}/event', [StudentController::class, 'logEvent'])->name('log-event')->middleware('throttle:60,1');
            Route::post('/exam/{exam}/submit', [StudentController::class, 'submitExam'])->name('submit-exam');
        });
    });
});
