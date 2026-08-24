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

it('renders the connection indicator and retry/queue client logic on the exam page', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    $html = test()->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    // Regression guard for Faza 2.4 - a PHP feature test can't execute the JS
    // (see the manually-run Node harness that exercised the actual retry/
    // backoff/race-condition logic against this same rendered output), but it
    // can catch someone accidentally deleting these pieces in a later edit.
    expect($html)->toContain('id="connection-dot"')
        ->and($html)->toContain('id="connection-label"')
        ->and($html)->toContain('function postAnswerWithRetry')
        ->and($html)->toContain('function flushAutosaveQueue')
        ->and($html)->toContain('err.status === 409');
});
