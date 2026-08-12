<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
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
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->restrictOnDelete();
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
        });
        Schema::table('exam_results', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
        });
        Schema::table('exam_results', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }
};
