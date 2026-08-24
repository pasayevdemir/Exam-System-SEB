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
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify questions table to support file upload type
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('question_type');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('question_type', ['single', 'multiple', 'file_upload'])->default('single')->after('question_text');
            $table->json('file_upload_settings')->nullable()->after('question_type'); // Store file type restrictions, max size, etc.
        });

        // Modify student_answers table to support file uploads
        Schema::table('student_answers', function (Blueprint $table) {
            $table->foreignId('answer_id')->nullable()->change();
            $table->string('file_path')->nullable()->after('answer_id');
            $table->string('original_filename')->nullable()->after('file_path');
            $table->integer('file_size')->nullable()->after('original_filename'); // in bytes
            $table->string('file_mime_type')->nullable()->after('file_size');
            $table->decimal('manual_score', 5, 2)->nullable()->after('file_mime_type'); // For manual grading
            $table->text('admin_feedback')->nullable()->after('manual_score');
            $table->boolean('is_graded')->default(false)->after('admin_feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropColumn([
                'file_path',
                'original_filename',
                'file_size',
                'file_mime_type',
                'manual_score',
                'admin_feedback',
                'is_graded',
            ]);
            $table->foreignId('answer_id')->nullable(false)->change();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['file_upload_settings']);
            $table->dropColumn('question_type');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('question_type', ['single', 'multiple'])->default('single')->after('question_text');
        });
    }
};
