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
        Schema::create('exam_question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained('question_banks')->restrictOnDelete();
            $table->unsignedInteger('quota_easy')->default(0);
            $table->unsignedInteger('quota_medium')->default(0);
            $table->unsignedInteger('quota_hard')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'question_bank_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_question_bank');
    }
};
