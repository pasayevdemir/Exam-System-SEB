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
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('completed_at');
            // Add the replacement index before dropping the unique one, since MySQL
            // uses the unique index to back the exam_id/user_id foreign keys and
            // refuses to drop it while no other index covers those columns.
            $table->index(['exam_id', 'user_id', 'superseded_at']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropUnique(['exam_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unique(['exam_id', 'user_id']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'user_id', 'superseded_at']);
            $table->dropColumn('superseded_at');
        });
    }
};
