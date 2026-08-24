<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Services;

use App\Models\AdminCredential;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * The single source of truth for the admin credential.
 *
 * Resolution rule: if an `admin_credentials` row exists it is authoritative for
 * BOTH username and password, and config('admin.*') is ignored entirely. If no
 * row exists, both come from config. The two are never mixed — a database
 * username paired with an environment password is a state nobody could explain
 * during an incident.
 *
 * The config values are therefore a bootstrap fallback only: they let the very
 * first login happen on a fresh deploy, and stop being consulted the moment the
 * admin saves a credential from the panel.
 *
 * Both gates go through here — the login check and the re-auth that guards bank
 * and exam deletion — so a changed password takes effect everywhere at once.
 */
class AdminCredentials
{
    private ?AdminCredential $row = null;

    private bool $loaded = false;

    /**
     * The stored row, or null when running on the config fallback.
     *
     * The table is read on every admin request, including the login POST. If a
     * deploy skips `php artisan migrate` an unguarded read would 500 the login
     * page itself and lock the admin out with no way back in, so a missing table
     * degrades to the fallback rather than throwing.
     */
    private function row(): ?AdminCredential
    {
        if ($this->loaded) {
            return $this->row;
        }

        $this->loaded = true;
        $this->row = Schema::hasTable('admin_credentials')
            ? AdminCredential::query()->first()
            : null;

        return $this->row;
    }

    public function isUsingEnvFallback(): bool
    {
        return $this->row() === null;
    }

    public function username(): string
    {
        return $this->row()?->username ?? (string) config('admin.username');
    }

    /**
     * Verify a full login. Both comparisons are evaluated before returning:
     * short-circuiting on a bad username would leak, through response timing,
     * whether the username alone was right.
     */
    public function verifyLogin(?string $username, ?string $password): bool
    {
        $usernameOk = is_string($username) && hash_equals($this->username(), $username);
        $passwordOk = $this->passwordMatches($password);

        return $usernameOk && $passwordOk;
    }

    /**
     * Verify the password on its own — used to re-authorise destructive actions,
     * where the admin is already signed in and only re-proves the password.
     */
    public function passwordMatches(?string $candidate): bool
    {
        if (! is_string($candidate) || $candidate === '') {
            return false;
        }

        $row = $this->row();

        if ($row !== null) {
            return Hash::check($candidate, $row->password);
        }

        // The config fallback is plaintext by nature, so Hash::check cannot apply.
        // hash_equals keeps the comparison constant-time all the same.
        return hash_equals((string) config('admin.password'), $candidate);
    }

    /**
     * Save the credential. A null password changes the username only, so the
     * admin can correct a username without rotating the password.
     */
    public function update(string $username, ?string $plainPassword = null): void
    {
        $row = $this->row();

        $attributes = ['username' => $username];

        if ($plainPassword !== null && $plainPassword !== '') {
            // Hashed by the model cast, never stored as typed.
            $attributes['password'] = $plainPassword;
        } elseif ($row === null) {
            // First save while still on the fallback: carry the environment
            // password over so a username-only change cannot lock the admin out.
            $attributes['password'] = (string) config('admin.password');
        }

        if ($row === null) {
            AdminCredential::create($attributes);
        } else {
            $row->update($attributes);
        }

        // Drop the memoised row: anything reading the credential later in this
        // same request must see what was just saved, not the pre-save state.
        $this->loaded = false;
        $this->row = null;
    }
}
