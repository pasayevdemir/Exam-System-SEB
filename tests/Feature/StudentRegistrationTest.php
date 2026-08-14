<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Aysel',
        'last_name' => 'Mammadova',
        'phone_number' => '+994501234567',
        'email' => 'aysel@example.com',
        'fin_code' => 'FIN1234',
        'password' => 'sup3r-secret',
        'password_confirmation' => 'sup3r-secret',
    ], $overrides);
}

it('signs the new student in and lands them on their profile', function () {
    test()->post(route('student.store'), registrationPayload())
        ->assertRedirect(route('student.profile'));

    $user = User::firstWhere('email', 'aysel@example.com');

    expect($user)->not->toBeNull();
    test()->assertAuthenticatedAs($user);
});

it('does not send the new student back to the sign-in form', function () {
    test()->post(route('student.store'), registrationPayload())
        ->assertRedirect(route('student.profile'));

    // The profile must actually be reachable straight afterwards — a redirect
    // that immediately bounces would be the same bug in a different shape.
    test()->get(route('student.profile'))->assertOk()->assertSee('Aysel');
});

it('stores the registration password as a hash', function () {
    test()->post(route('student.store'), registrationPayload());

    $user = User::firstWhere('email', 'aysel@example.com');

    expect($user->password)->not->toBe('sup3r-secret');
    expect(Hash::check('sup3r-secret', $user->password))->toBeTrue();
});

it('lets the new student sign in with the password they chose', function () {
    test()->post(route('student.store'), registrationPayload());
    test()->post(route('student.logout'));

    test()->post(route('student.authenticate'), [
        'email' => 'aysel@example.com',
        'password' => 'sup3r-secret',
    ])->assertRedirect(route('student.exams'));
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'aysel@example.com']);

    test()->post(route('student.store'), registrationPayload())
        ->assertSessionHasErrors('email');

    test()->assertGuest();
});

it('rejects a duplicate fin code', function () {
    User::factory()->create(['fin_code' => 'FIN1234']);

    test()->post(route('student.store'), registrationPayload())
        ->assertSessionHasErrors('fin_code');

    test()->assertGuest();
});

/* -------------------------------------------------------------------------- */
/* Admin session left over in the same browser                                */
/* -------------------------------------------------------------------------- */

it('clears an admin session when someone registers in the same browser', function () {
    // Without this, StudentMiddleware bounces the new student straight back out
    // and the account looks like it was never created.
    test()->withSession(['admin_logged_in' => true])
        ->post(route('student.store'), registrationPayload())
        ->assertRedirect(route('student.profile'))
        ->assertSessionHas('warning');

    test()->get(route('student.profile'))->assertOk();
});

it('clears an admin session when a student signs in in the same browser', function () {
    User::factory()->create([
        'email' => 'aysel@example.com',
        'password' => 'sup3r-secret',
    ]);

    test()->withSession(['admin_logged_in' => true])
        ->post(route('student.authenticate'), [
            'email' => 'aysel@example.com',
            'password' => 'sup3r-secret',
        ])->assertRedirect(route('student.exams'));

    test()->get(route('student.exams'))->assertOk();
});
