<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\AdminCredential;
use App\Models\QuestionBank;
use App\Services\AdminCredentials;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

const ENV_USERNAME = 'env-admin';
const ENV_PASSWORD = 'env-password-value';

beforeEach(function () {
    config(['admin.username' => ENV_USERNAME, 'admin.password' => ENV_PASSWORD]);
});

function signInAsAdmin(string $username, string $password)
{
    return test()->post(route('admin.authenticate'), [
        'username' => $username,
        'password' => $password,
    ]);
}

/* -------------------------------------------------------------------------- */
/* Resolution: database row wins, config is a bootstrap fallback              */
/* -------------------------------------------------------------------------- */

it('logs the admin in with the environment credentials when no database credential exists', function () {
    signInAsAdmin(ENV_USERNAME, ENV_PASSWORD)
        ->assertRedirect(route('admin.dashboard'));

    expect(session('admin_logged_in'))->toBeTrue();
});

it('prefers the database credential over the environment fallback', function () {
    AdminCredential::create(['username' => 'db-admin', 'password' => 'db-password-value']);

    // The environment pair must now be rejected outright.
    signInAsAdmin(ENV_USERNAME, ENV_PASSWORD)->assertSessionHas('error');
    expect(session('admin_logged_in'))->toBeNull();

    signInAsAdmin('db-admin', 'db-password-value')->assertRedirect(route('admin.dashboard'));
});

it('rejects a correct password with the wrong username', function () {
    signInAsAdmin('not-the-admin', ENV_PASSWORD)->assertSessionHas('error');

    expect(session('admin_logged_in'))->toBeNull();
});

it('falls back to the environment when the credentials table is missing', function () {
    // Mirrors a deploy where "php artisan migrate" was skipped: this must not
    // 500 the login page, or the panel becomes unreachable.
    Schema::drop('admin_credentials');

    signInAsAdmin(ENV_USERNAME, ENV_PASSWORD)->assertRedirect(route('admin.dashboard'));
});

/* -------------------------------------------------------------------------- */
/* One source of truth for both gates                                         */
/* -------------------------------------------------------------------------- */

it('gates a bank deletion on the database password once one is set', function () {
    AdminCredential::create(['username' => 'db-admin', 'password' => 'db-password-value']);
    $bank = QuestionBank::factory()->create();

    // The old environment password no longer authorises anything.
    test()->withSession(['admin_logged_in' => true])
        ->delete(route('admin.delete-bank', $bank->id), ['admin_password' => ENV_PASSWORD])
        ->assertSessionHas('error');

    expect(QuestionBank::find($bank->id))->not->toBeNull();

    test()->withSession(['admin_logged_in' => true])
        ->delete(route('admin.delete-bank', $bank->id), ['admin_password' => 'db-password-value'])
        ->assertSessionHas('success');

    expect(QuestionBank::find($bank->id))->toBeNull();
});

/* -------------------------------------------------------------------------- */
/* Changing the credential                                                    */
/* -------------------------------------------------------------------------- */

it('stores the new admin password as a hash, never as plaintext', function () {
    test()->withSession(['admin_logged_in' => true])
        ->put(route('admin.update-credentials'), [
            'current_password' => ENV_PASSWORD,
            'username' => ENV_USERNAME,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHas('success');

    $stored = AdminCredential::first();

    expect($stored->password)->not->toBe('a-brand-new-password');
    expect(Hash::check('a-brand-new-password', $stored->password))->toBeTrue();
});

it('refuses a credential change when the current password is wrong', function () {
    test()->withSession(['admin_logged_in' => true])
        ->put(route('admin.update-credentials'), [
            'current_password' => 'wrong-current',
            'username' => 'hacked',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('current_password');

    expect(AdminCredential::count())->toBe(0);
});

it('rejects a new password shorter than twelve characters', function () {
    test()->withSession(['admin_logged_in' => true])
        ->put(route('admin.update-credentials'), [
            'current_password' => ENV_PASSWORD,
            'username' => ENV_USERNAME,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

    expect(AdminCredential::count())->toBe(0);
});

it('lets the admin change the username and sign in with the new one', function () {
    test()->withSession(['admin_logged_in' => true])
        ->put(route('admin.update-credentials'), [
            'current_password' => ENV_PASSWORD,
            'username' => 'renamed-admin',
            // No password fields: a username-only change.
        ])->assertSessionHas('success');

    signInAsAdmin('renamed-admin', ENV_PASSWORD)->assertRedirect(route('admin.dashboard'));
});

it('keeps the settings page behind admin auth', function () {
    test()->get(route('admin.settings'))->assertRedirect(route('admin.login'));
});

it('renders the settings page with the current username', function () {
    AdminCredential::create(['username' => 'db-admin', 'password' => 'db-password-value']);

    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('db-admin', false)
        ->assertSee('name="current_password"', false);
});

/* -------------------------------------------------------------------------- */
/* Fallback warning                                                           */
/* -------------------------------------------------------------------------- */

it('warns on the dashboard while the admin password comes from the environment', function () {
    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('will be lost on the next redeploy');
});

it('stops warning once a database credential exists', function () {
    AdminCredential::create(['username' => 'db-admin', 'password' => 'db-password-value']);

    test()->withSession(['admin_logged_in' => true])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('will be lost on the next redeploy');
});

it('reports whether it is running on the environment fallback', function () {
    $credentials = app(AdminCredentials::class);

    expect($credentials->isUsingEnvFallback())->toBeTrue();

    $credentials->update('db-admin', 'db-password-value');

    expect($credentials->isUsingEnvFallback())->toBeFalse()
        ->and($credentials->username())->toBe('db-admin');
});
