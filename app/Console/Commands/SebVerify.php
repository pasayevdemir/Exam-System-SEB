<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SebVerify extends Command
{
    protected $signature = 'seb:verify';

    protected $description = 'Pre-exam smoke test: SEB config, HTTPS reachability, DB, storage, and required PHP extensions.';

    public function handle(): int
    {
        $this->info('Running pre-exam checks...');
        $ok = true;

        $ok = $this->check('APP_URL uses https', str_starts_with(config('app.url'), 'https://')) && $ok;

        if (config('seb.required')) {
            $hasKey = (bool) config('seb.config_key');
            $ok = $this->check('SEB_CONFIG_KEY is set (seb.required=true)', $hasKey) && $ok;
            if (!$hasKey) {
                $this->line('    seb.required=true with no key means every student gets a 503 (fail-closed).');
            }
        } else {
            $this->line('  <fg=yellow>seb.required is false — SEB is NOT currently enforced.</>');
        }

        // A console command has no incoming HTTP request, so it can't directly
        // inspect what request()->fullUrl() resolves to behind the proxy chain
        // the way a real browser hit could. The closest equivalent: actually
        // reach the public URL over HTTPS and confirm it responds - this is
        // what catches a broken TrustProxies/nginx/Cloudflare chain in
        // practice, even though it isn't a literal fullUrl() scheme check.
        try {
            $response = Http::timeout(10)->get(rtrim(config('app.url'), '/') . '/up');
            $ok = $this->check('Public APP_URL/up reachable over HTTPS (200)', $response->successful()) && $ok;
        } catch (Throwable $e) {
            $this->check('Public APP_URL/up reachable over HTTPS (200)', false);
            $this->line('    ' . $e->getMessage());
            $ok = false;
        }

        if (config('session.driver') === 'database') {
            $ok = $this->check('sessions table exists', Schema::hasTable('sessions')) && $ok;
        }

        $ok = $this->checkStorageWritable() && $ok;

        foreach (['pdo_mysql', 'mbstring', 'exif', 'pcntl', 'bcmath', 'gd', 'zip', 'xml', 'xmlwriter'] as $ext) {
            $ok = $this->check("PHP extension loaded: {$ext}", extension_loaded($ext)) && $ok;
        }

        try {
            DB::connection()->getPdo();
            $ok = $this->check('Database connection', true) && $ok;
        } catch (Throwable $e) {
            $ok = $this->check('Database connection', false) && $ok;
            $this->line('    ' . $e->getMessage());
        }

        $this->newLine();

        if ($ok) {
            $this->info('All checks passed.');
            return self::SUCCESS;
        }

        $this->error('One or more checks failed — resolve before relying on this for an exam.');
        return self::FAILURE;
    }

    private function checkStorageWritable(): bool
    {
        try {
            Storage::disk('local')->put('.seb_verify_tmp', 'ok');
            $written = Storage::disk('local')->get('.seb_verify_tmp') === 'ok';
            Storage::disk('local')->delete('.seb_verify_tmp');

            return $this->check('storage/app/private is writable', $written);
        } catch (Throwable $e) {
            $this->check('storage/app/private is writable', false);
            $this->line('    ' . $e->getMessage());

            return false;
        }
    }

    private function check(string $label, bool $passed): bool
    {
        $icon = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $this->line("  {$icon} {$label}");

        return $passed;
    }
}
