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
        Schema::table('exam_results', function (Blueprint $table) {
            $table->index(['exam_id', 'submitted_at']);
            $table->index(['exam_id', 'user_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'submitted_at']);
            $table->dropIndex(['exam_id', 'user_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
