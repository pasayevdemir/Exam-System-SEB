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
     * Students cannot reset their own password - they raise a request here and an
     * admin approves it. Laravel's stock password_reset_tokens table is a
     * self-service token store and does not model the approval step, so this is
     * a separate table rather than a reuse of it.
     */
    public function up(): void
    {
        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // The admin queue reads pending-first; the user pair backs the
            // "does this student already have one open?" check on submit.
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
