<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One attempt can only ever produce one result.
     *
     * StudentController::submitExam() now takes the attempt row under a lock
     * before writing, which closes the double-submit race in application code.
     * This is the backstop underneath it: whatever else goes wrong — a future
     * code path that forgets the lock, a manual fix-up, a replayed request on a
     * database where the lock is a no-op — the second row is refused outright.
     *
     * The column is nullable and stays that way. Results created before attempts
     * existed carry NULL, and both MySQL and SQLite allow any number of NULLs in
     * a unique index, so those rows are untouched. A retake supersedes the old
     * attempt and generates a new one, so it arrives with a different
     * exam_attempt_id and is unaffected.
     */
    public function up(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->unique('exam_attempt_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The plain index has to go in first. Once the unique index exists, MySQL
     * uses it to back the exam_attempt_id foreign key and refuses to drop it
     * while nothing else covers the column — the same trap the superseded_at
     * migration hit on exam_attempts, here with error 1553.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('exam_results', 'exam_results_exam_attempt_id_index')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->index('exam_attempt_id');
            });
        }

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropUnique(['exam_attempt_id']);
        });
    }
};
