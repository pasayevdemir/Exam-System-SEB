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
        Schema::table('exam_results', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('exam_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->string('student_id')->nullable()->change();
            $table->string('index_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->string('student_id')->nullable(false)->change();
            $table->string('index_no')->nullable(false)->change();
        });
    }
};
