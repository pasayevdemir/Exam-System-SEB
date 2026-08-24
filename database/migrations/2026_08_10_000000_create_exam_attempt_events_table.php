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
    public function up(): void
    {
        Schema::create('exam_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['exam_attempt_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_events');
    }
};
