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
     * The admin is not a `users` row — every row in that table is a student, and
     * there is no role column. Until now the single admin credential lived only
     * in ADMIN_USERNAME/ADMIN_PASSWORD, which meant it could not be changed from
     * the panel and did not survive a redeploy (the image is immutable and the
     * container's .env is rebuilt from the host).
     *
     * This table holds at most ONE row, by convention rather than by constraint —
     * AdminCredentials reads `first()` and writes through `updateOrCreate`.
     *
     * Deliberately NOT seeded from config here: baking the deploy-time plaintext
     * into the database would freeze a possibly-wrong value and permanently kill
     * the env fallback that makes first-time login work.
     */
    public function up(): void
    {
        Schema::create('admin_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('password'); // bcrypt hash, via the model's 'hashed' cast
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_credentials');
    }
};
