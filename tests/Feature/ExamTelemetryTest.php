<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\ExamAttemptEvent;
use App\Models\ExamResult;
use App\Models\User;

function logExamEvent(User $user, $exam, string $type)
{
    return test()->actingAs($user)
        ->postJson(route('student.log-event', $exam->id), ['type' => $type]);
}

it('records a valid anti-cheat event against the active attempt', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    logExamEvent($user, $seed['exam'], 'tab_hidden')
        ->assertOk()
        ->assertJson(['success' => true]);

    $event = ExamAttemptEvent::first();
    expect($event)->not->toBeNull()
        ->and($event->exam_attempt_id)->toBe($seed['attempt']->id)
        ->and($event->type)->toBe('tab_hidden');
});

it('rejects an event type outside the known allow-list', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    logExamEvent($user, $seed['exam'], 'not_a_real_event')->assertStatus(422);

    expect(ExamAttemptEvent::count())->toBe(0);
});

it('rejects an event when there is no active attempt', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['completed_at' => now()]);

    logExamEvent($user, $seed['exam'], 'tab_hidden')->assertStatus(409);

    expect(ExamAttemptEvent::count())->toBe(0);
});

it('shows the violation count and timeline on the exam results page', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    $seed['attempt']->events()->createMany([
        ['type' => 'tab_hidden', 'occurred_at' => now()->subMinutes(2)],
        ['type' => 'copy', 'occurred_at' => now()->subMinute()],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    $html = test()
        ->withSession(['admin_logged_in' => true])
        ->get(route('admin.exam-results', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('>2</span>')
        ->and($html)->toContain('Violation Timeline')
        ->and($html)->toContain('Tab hidden')
        ->and($html)->toContain('Copy');
});

it('shows a zero-violation badge when the attempt has no events', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    $html = test()
        ->withSession(['admin_logged_in' => true])
        ->get(route('admin.exam-results', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('bg-secondary">0</span>')
        ->and($html)->not->toContain('Violation Timeline');
});
