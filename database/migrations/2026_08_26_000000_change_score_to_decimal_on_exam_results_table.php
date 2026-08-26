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
     * Scores stopped being a count of right answers and became a sum of question
     * weights, and those are 1.0 / 1.5 / 2.0 - so the integer column this reverts
     * would truncate every attempt containing an odd number of medium questions.
     *
     * Widening integer -> decimal(8,2) keeps every existing value intact: a stored
     * 17 reads back as 17.00, which is still the same mark on an all-easy exam.
     */
    public function up(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->integer('score')->change();
        });
    }
};
