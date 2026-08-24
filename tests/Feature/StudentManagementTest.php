<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function asAdmin()
{
    return test()->withSession(['admin_logged_in' => true]);
}

it('lists students on the admin students page', function () {
    $student = User::factory()->create(['first_name' => 'Aysel', 'last_name' => 'Mammadova']);

    asAdmin()->get(route('admin.students'))
        ->assertOk()
        ->assertSee('Aysel Mammadova')
        ->assertSee($student->email);
});

it('filters the student list by search term', function () {
    User::factory()->create(['first_name' => 'Aysel', 'last_name' => 'Mammadova']);
    User::factory()->create(['first_name' => 'Rashad', 'last_name' => 'Aliyev']);

    asAdmin()->get(route('admin.students', ['search' => 'Rashad']))
        ->assertOk()
        ->assertSee('Rashad Aliyev')
        ->assertDontSee('Aysel Mammadova');
});

it('updates a student profile', function () {
    $student = User::factory()->create(['first_name' => 'Aysel', 'phone_number' => '0501112233']);

    asAdmin()->put(route('admin.update-student', $student->id), [
        'first_name' => 'Aysel',
        'last_name' => 'Mammadova',
        'email' => 'aysel@example.com',
        'phone_number' => '0555554433',
        'fin_code' => 'FIN12345',
    ])->assertRedirect(route('admin.students'));

    expect($student->fresh())
        ->last_name->toBe('Mammadova')
        ->email->toBe('aysel@example.com')
        ->phone_number->toBe('0555554433');
});

it('rejects a student email that belongs to someone else', function () {
    $taken = User::factory()->create(['email' => 'taken@example.com']);
    $student = User::factory()->create();

    asAdmin()->put(route('admin.update-student', $student->id), [
        'first_name' => 'A',
        'last_name' => 'B',
        'email' => $taken->email,
        'phone_number' => '0501112233',
        'fin_code' => 'FIN99999',
    ])->assertSessionHasErrors('email');

    expect($student->fresh()->email)->not->toBe('taken@example.com');
});

it('lets a student keep their own email when updating', function () {
    $student = User::factory()->create(['email' => 'mine@example.com']);

    asAdmin()->put(route('admin.update-student', $student->id), [
        'first_name' => 'Aysel',
        'last_name' => 'Mammadova',
        'email' => 'mine@example.com',
        'phone_number' => '0501112233',
        'fin_code' => $student->fin_code,
    ])->assertSessionHasNoErrors();
});

it('refuses to delete a student who has exam attempts', function () {
    $student = User::factory()->create();
    $exam = Exam::factory()->create();

    ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $student->id,
        'started_at' => now(),
    ]);

    asAdmin()->delete(route('admin.delete-student', $student->id))
        ->assertRedirect(route('admin.students'))
        ->assertSessionHas('error');

    expect(User::find($student->id))->not->toBeNull();
});

it('deletes a student with no exam history', function () {
    $student = User::factory()->create();

    asAdmin()->delete(route('admin.delete-student', $student->id))
        ->assertSessionHas('success');

    expect(User::find($student->id))->toBeNull();
});

it('keeps the students page behind admin auth', function () {
    test()->get(route('admin.students'))->assertRedirect(route('admin.login'));
});

// ── Password reset requests ────────────────────────────────────────────────

it('records a pending request when a student asks for a reset', function () {
    $student = User::factory()->create(['email' => 'aysel@example.com']);

    test()->post(route('student.password-request.store'), ['email' => 'aysel@example.com'])
        ->assertRedirect(route('student.login'));

    expect(PasswordResetRequest::where('user_id', $student->id)->pending()->count())->toBe(1);
});

it('does not reveal whether an email is registered', function () {
    $known = test()->post(route('student.password-request.store'), ['email' => 'nobody@example.com']);

    expect(PasswordResetRequest::count())->toBe(0);
    $known->assertRedirect(route('student.login'))->assertSessionHas('success');
});

it('does not stack duplicate pending requests for one student', function () {
    $student = User::factory()->create(['email' => 'aysel@example.com']);

    test()->post(route('student.password-request.store'), ['email' => 'aysel@example.com']);
    test()->post(route('student.password-request.store'), ['email' => 'aysel@example.com']);

    expect(PasswordResetRequest::where('user_id', $student->id)->pending()->count())->toBe(1);
});

it('issues a working temporary password when an admin approves', function () {
    $student = User::factory()->create(['password' => Hash::make('old-password-123')]);
    $resetRequest = PasswordResetRequest::create(['user_id' => $student->id]);

    $response = asAdmin()->post(route('admin.approve-reset-request', $resetRequest->id))
        ->assertRedirect(route('admin.students'))
        ->assertSessionHas('temporary_password');

    $issued = $response->getSession()->get('temporary_password');

    expect($resetRequest->fresh()->status)->toBe(PasswordResetRequest::STATUS_APPROVED)
        ->and($resetRequest->fresh()->resolved_at)->not->toBeNull()
        ->and(Hash::check('old-password-123', $student->fresh()->password))->toBeFalse()
        ->and(Hash::check($issued['password'], $student->fresh()->password))->toBeTrue();
});

it('lets a student sign in with the issued temporary password', function () {
    $student = User::factory()->create();
    $resetRequest = PasswordResetRequest::create(['user_id' => $student->id]);

    $issued = asAdmin()->post(route('admin.approve-reset-request', $resetRequest->id))
        ->getSession()->get('temporary_password');

    test()->post(route('student.authenticate'), [
        'email' => $student->email,
        'password' => $issued['password'],
    ])->assertRedirect(route('student.exams'));
});

it('will not approve the same request twice', function () {
    $student = User::factory()->create();
    $resetRequest = PasswordResetRequest::create([
        'user_id' => $student->id,
        'status' => PasswordResetRequest::STATUS_APPROVED,
        'resolved_at' => now(),
    ]);

    asAdmin()->post(route('admin.approve-reset-request', $resetRequest->id))
        ->assertSessionHas('error');
});

it('rejects a request without touching the password', function () {
    $student = User::factory()->create(['password' => Hash::make('old-password-123')]);
    $resetRequest = PasswordResetRequest::create(['user_id' => $student->id]);

    asAdmin()->post(route('admin.reject-reset-request', $resetRequest->id))
        ->assertSessionHas('success');

    expect($resetRequest->fresh()->status)->toBe(PasswordResetRequest::STATUS_REJECTED)
        ->and(Hash::check('old-password-123', $student->fresh()->password))->toBeTrue();
});

// ── Direct password setting ────────────────────────────────────────────────

it('sets a password the admin typed', function () {
    $student = User::factory()->create(['password' => Hash::make('old-password-123')]);

    asAdmin()->from(route('admin.edit-student', $student->id))
        ->post(route('admin.set-student-password', $student->id), [
            'mode' => 'manual',
            'password' => 'chosen-by-admin',
        ])->assertSessionHas('success');

    expect(Hash::check('chosen-by-admin', $student->fresh()->password))->toBeTrue();
});

it('never echoes a manually set password back into the session', function () {
    $student = User::factory()->create();

    $response = asAdmin()->from(route('admin.edit-student', $student->id))
        ->post(route('admin.set-student-password', $student->id), [
            'mode' => 'manual',
            'password' => 'chosen-by-admin',
        ]);

    expect($response->getSession()->get('success'))->not->toContain('chosen-by-admin');
    expect($response->getSession()->has('temporary_password'))->toBeFalse();
});

it('rejects a manual password shorter than eight characters', function () {
    $student = User::factory()->create(['password' => Hash::make('old-password-123')]);

    asAdmin()->from(route('admin.edit-student', $student->id))
        ->post(route('admin.set-student-password', $student->id), [
            'mode' => 'manual',
            'password' => 'short',
        ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password-123', $student->fresh()->password))->toBeTrue();
});

it('sets the password to the student FIN code on request', function () {
    $student = User::factory()->create(['fin_code' => 'FIN7A2B9']);

    asAdmin()->from(route('admin.students'))
        ->post(route('admin.set-student-password', $student->id), ['mode' => 'fin'])
        ->assertSessionHas('success');

    expect(Hash::check('FIN7A2B9', $student->fresh()->password))->toBeTrue();
});

it('lets a student sign in with their FIN code once it is set as the password', function () {
    $student = User::factory()->create(['fin_code' => 'FIN7A2B9']);

    asAdmin()->post(route('admin.set-student-password', $student->id), ['mode' => 'fin']);

    test()->post(route('student.authenticate'), [
        'email' => $student->email,
        'password' => 'FIN7A2B9',
    ])->assertRedirect(route('student.exams'));
});

it('closes a pending reset request when the admin sets a password directly', function () {
    $student = User::factory()->create(['fin_code' => 'FIN7A2B9']);
    $resetRequest = PasswordResetRequest::create(['user_id' => $student->id]);

    asAdmin()->post(route('admin.set-student-password', $student->id), ['mode' => 'fin']);

    expect($resetRequest->fresh()->status)->toBe(PasswordResetRequest::STATUS_APPROVED)
        ->and($resetRequest->fresh()->resolved_at)->not->toBeNull();
});

it('keeps direct password setting behind admin auth', function () {
    $student = User::factory()->create(['password' => Hash::make('old-password-123')]);

    test()->post(route('admin.set-student-password', $student->id), ['mode' => 'fin'])
        ->assertRedirect(route('admin.login'));

    expect(Hash::check('old-password-123', $student->fresh()->password))->toBeTrue();
});

it('offers both FIN and manual password controls on the edit page', function () {
    $student = User::factory()->create();

    asAdmin()->get(route('admin.edit-student', $student->id))
        ->assertOk()
        ->assertSee('Use FIN Code as Password')
        ->assertSee('Set Password');
});

it('shows pending requests on the students page', function () {
    $student = User::factory()->create(['first_name' => 'Aysel', 'last_name' => 'Mammadova']);
    PasswordResetRequest::create(['user_id' => $student->id]);

    asAdmin()->get(route('admin.students'))
        ->assertOk()
        ->assertSee('Pending Password Reset Requests')
        ->assertSee('Aysel Mammadova');
});
