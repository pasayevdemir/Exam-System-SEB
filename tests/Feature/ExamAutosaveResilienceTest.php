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

    // The retry, backoff and queue behaviour this used to assert on by reading
    // the inline script is now covered for real in tests/js/exam/autosave.test.js,
    // which executes it. What is left here is the half a PHP test can still
    // own: the markup those modules bind to, and the queue key the server
    // chooses, which is per-exam so two open exams cannot share a queue.
    expect($html)->toContain('id="connection-dot"')
        ->and($html)->toContain('id="connection-label"')
        ->and($html)->toContain('id="autosave-status"')
        ->and($html)->toContain('"queueKey":"examAutosaveQueue_'.$seed['exam']->id.'"')
        ->and($html)->toContain('"autosaveUrl":');
});
