<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\User;

/**
 * The exam page shows a countdown that must agree with expires_at. Its own
 * interval cannot be trusted (background tabs throttle it, a suspended laptop
 * stops it, a dropped connection hides the divergence), so the page re-anchors
 * itself on the server's reading. These cover the server half of that contract.
 */
it('reports the remaining time on keep-alive', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['expires_at' => now()->addMinutes(10)]);

    $remaining = test()->actingAs($user)
        ->getJson(route('student.keep-alive'))
        ->assertOk()
        ->json('remaining_seconds');

    expect($remaining)->toBeGreaterThan(590)->toBeLessThanOrEqual(600);
});

it('reports a null clock for an untimed attempt', function () {
    $user = User::factory()->create();
    seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    test()->actingAs($user)
        ->getJson(route('student.keep-alive'))
        ->assertOk()
        ->assertJson(['remaining_seconds' => null]);
});

it('reports zero rather than a negative clock inside the grace period', function () {
    config(['exam.grace_period_seconds' => 30]);

    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['expires_at' => now()->subSeconds(10)]);

    // Still in progress through the grace period - this is exactly the window
    // where the page is running its auto-submit countdown.
    test()->actingAs($user)
        ->getJson(route('student.keep-alive'))
        ->assertOk()
        ->assertJson(['remaining_seconds' => 0]);
});

it('carries the clock on the autosave response', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['expires_at' => now()->addMinutes(5)]);

    // Autosave is the page's most frequent round trip, so it doubles as the
    // routine re-sync channel and must always carry the figure.
    $remaining = test()->actingAs($user)
        ->postJson(route('student.autosave-answer', $seed['exam']->id), [
            'question_id' => $seed['questions'][0]['question']->id,
            'answer_indexes' => [0],
        ])
        ->assertOk()
        ->json('remaining_seconds');

    expect($remaining)->toBeGreaterThan(290)->toBeLessThanOrEqual(300);
});

it('drives the on-page countdown from a deadline rather than a per-tick decrement', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['expires_at' => now()->addMinutes(10)]);
    $seed['exam']->update(['time_limit_minutes' => 10]);

    $html = test()->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    // Same intent as the ExamAutosaveResilienceTest guard: a PHP test can't run
    // the JS, but it can stop these pieces being quietly dropped in a later edit.
    expect($html)->toContain('deadline = Date.now()')
        ->and($html)->toContain('onServerClock = function');
});
