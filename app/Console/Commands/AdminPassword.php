<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Console\Commands;

use App\Services\AdminCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Set the admin credential from the server, without ever putting the plaintext
 * in .env or in shell history.
 *
 * This is the only recovery route once a credential row exists: from that point
 * the ADMIN_PASSWORD fallback is no longer consulted, so a forgotten password
 * cannot be fixed by editing the environment file.
 *
 * Usage (the -it is required — the prompts read from the terminal):
 *   docker exec -it exam_app php artisan admin:password
 */
class AdminPassword extends Command
{
    protected $signature = 'admin:password {--username= : Set the admin username at the same time}';

    protected $description = 'Set the admin panel password (and optionally the username).';

    public function handle(AdminCredentials $credentials): int
    {
        if (! Schema::hasTable('admin_credentials')) {
            $this->error('The admin_credentials table is missing. Run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $username = $this->option('username') ?: $credentials->username();

        $password = $this->secret('New admin password (at least 12 characters)');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password must be at least 12 characters. Nothing was changed.');

            return self::FAILURE;
        }

        if ($password !== $this->secret('Confirm the password')) {
            $this->error('The passwords did not match. Nothing was changed.');

            return self::FAILURE;
        }

        $wasFallback = $credentials->isUsingEnvFallback();

        $credentials->update($username, $password);

        $this->info("Admin credentials saved for username \"{$username}\".");

        if ($wasFallback) {
            $this->line('  ADMIN_PASSWORD in the environment file is no longer used.');
        }

        return self::SUCCESS;
    }
}
