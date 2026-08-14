<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function profilePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Aysel',
        'last_name' => 'Mammadova',
        'phone_number' => '+994501111111',
        'email' => 'aysel@example.com',
        'fin_code' => 'FIN1234',
    ], $overrides);
}

/** Gives the student real exam history, which is what freezes the FIN code. */
function giveExamHistory(User $user): void
{
    ExamAttempt::create([
        'exam_id' => Exam::factory()->create()->id,
        'user_id' => $user->id,
        'started_at' => now(),
        'target_weight' => 10,
    ]);
}

/* -------------------------------------------------------------------------- */
/* Viewing                                                                    */
/* -------------------------------------------------------------------------- */

it('shows the signed-in student their own details', function () {
    $user = User::factory()->create(['first_name' => 'Aysel', 'fin_code' => 'FIN1234']);

    test()->actingAs($user)->get(route('student.profile'))
        ->assertOk()
        ->assertSee('Aysel')
        ->assertSee('FIN1234');
});

it('redirects a guest away from the profile page', function () {
    test()->get(route('student.profile'))->assertRedirect(route('student.login'));
});

it('links to the profile from the student navigation', function () {
    $user = User::factory()->create();

    test()->actingAs($user)->get(route('student.exams'))
        ->assertOk()
        ->assertSee(route('student.profile'), false);
});

/* -------------------------------------------------------------------------- */
/* Editing details                                                            */
/* -------------------------------------------------------------------------- */

it('updates the student profile', function () {
    $user = User::factory()->create();

    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload(['first_name' => 'Renamed']))
        ->assertSessionHas('success');

    expect($user->fresh()->first_name)->toBe('Renamed');
});

it('lets a student keep their own email address', function () {
    $user = User::factory()->create(['email' => 'aysel@example.com']);

    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload())
        ->assertSessionHasNoErrors();
});

it('rejects an email that belongs to another student', function () {
    $user = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload(['email' => 'taken@example.com']))
        ->assertSessionHasErrors('email');
});

it('rejects a fin code that belongs to another student', function () {
    $user = User::factory()->create();
    User::factory()->create(['fin_code' => 'TAKEN99']);

    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload(['fin_code' => 'TAKEN99']))
        ->assertSessionHasErrors('fin_code');
});

/* -------------------------------------------------------------------------- */
/* FIN code freezes once results exist                                        */
/* -------------------------------------------------------------------------- */

it('lets a student fix their fin code before they sit an exam', function () {
    $user = User::factory()->create(['fin_code' => 'TYPO123']);

    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload(['fin_code' => 'FIXED456']))
        ->assertSessionHas('success');

    expect($user->fresh()->fin_code)->toBe('FIXED456');
});

it('ignores a fin code change once the student has exam history', function () {
    $user = User::factory()->create(['fin_code' => 'ORIGINAL']);
    giveExamHistory($user);

    // The form disables the field, but a hand-crafted POST still carries it —
    // this asserts the server, not the markup, is what enforces the freeze.
    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload(['fin_code' => 'REWRITTEN']))
        ->assertSessionHas('success');

    expect($user->fresh()->fin_code)->toBe('ORIGINAL');
});

it('still lets the rest of the profile change after exam history exists', function () {
    $user = User::factory()->create(['fin_code' => 'ORIGINAL']);
    giveExamHistory($user);

    test()->actingAs($user)
        ->put(route('student.update-profile'), profilePayload(['first_name' => 'Renamed']))
        ->assertSessionHas('success');

    expect($user->fresh()->first_name)->toBe('Renamed');
});

it('shows the fin code as locked once the student has exam history', function () {
    $user = User::factory()->create();
    giveExamHistory($user);

    test()->actingAs($user)->get(route('student.profile'))
        ->assertOk()
        ->assertSee('can no longer be changed here');
});

/* -------------------------------------------------------------------------- */
/* Changing your own password                                                 */
/* -------------------------------------------------------------------------- */

it('changes the password when the current password is correct', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    test()->actingAs($user)->put(route('student.update-password'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHas('success');

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('refuses a password change when the current password is wrong', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $before = $user->password;

    test()->actingAs($user)->put(route('student.update-password'), [
        'current_password' => 'not-the-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect($user->fresh()->password)->toBe($before);
});

it('refuses a password change when the confirmation does not match', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    test()->actingAs($user)->put(route('student.update-password'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('resolves a pending reset request when the student changes their own password', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $pending = PasswordResetRequest::create([
        'user_id' => $user->id,
        'status' => PasswordResetRequest::STATUS_PENDING,
    ]);

    test()->actingAs($user)->put(route('student.update-password'), [
        'current_password' => 'old-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHas('success');

    // Otherwise it sits in the admin queue and the navbar badge burns forever.
    expect($pending->fresh()->isPending())->toBeFalse();
});

it('keeps password changes behind student auth', function () {
    test()->put(route('student.update-password'), [])->assertRedirect(route('student.login'));
});
