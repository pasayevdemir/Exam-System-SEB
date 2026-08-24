<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\ExamAttemptAnswer;
use App\Models\ExamResult;
use App\Models\StudentAnswer;
use App\Models\User;

it('rejects autosave once the attempt has expired', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['expires_at' => now()->subMinute()]);

    $response = test()->actingAs($user)
        ->postJson(route('student.autosave-answer', $seed['exam']->id), [
            'question_id' => $seed['questions'][0]['question']->id,
            'answer_indexes' => [0],
        ]);

    $response->assertStatus(409);
    expect(ExamAttemptAnswer::count())->toBe(0);
});

it('allows autosave inside the grace period after expires_at', function () {
    config(['exam.grace_period_seconds' => 30]);

    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['attempt']->update(['expires_at' => now()->subSeconds(10)]);

    $response = test()->actingAs($user)
        ->postJson(route('student.autosave-answer', $seed['exam']->id), [
            'question_id' => $seed['questions'][0]['question']->id,
            'answer_indexes' => [0],
        ]);

    $response->assertOk();
});

it('scores an expired submission from autosaved drafts, not the request body', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);
    $q = $seed['questions'][0];

    // Autosaved the wrong answer before time ran out.
    ExamAttemptAnswer::create([
        'exam_attempt_id' => $seed['attempt']->id,
        'exam_attempt_question_id' => $seed['attempt']->attemptQuestions()->first()->id,
        'selected_answer_ids' => [$q['answers'][0]->id],
    ]);

    $seed['attempt']->update(['expires_at' => now()->subMinute()]);

    // A manipulated late request claims the correct answer - must be ignored.
    submitAnswers($user, $seed['exam'], [
        'answers' => [$q['question']->id => 2],
        'auto_submit' => '1',
    ]);

    // Scored from the draft (wrong answer), not the request body (correct answer).
    expect(ExamResult::first()->score)->toBe(0)
        ->and(ExamResult::first()->studentAnswers->first()->answer_id)->toBe($q['answers'][0]->id);
});

it('does not let a late manual submit bypass expiry by omitting auto_submit', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [2]],
    ]);
    $q = $seed['questions'][0];
    $seed['attempt']->update(['expires_at' => now()->subMinute()]);

    // No draft exists at all - a late request without auto_submit still must not
    // be scored from its own body.
    submitAnswers($user, $seed['exam'], [
        'answers' => [$q['question']->id => 2],
    ]);

    expect(ExamResult::first()->score)->toBe(0);
});

it('records an explicit not-submitted row for an unfinished file upload on auto-submit', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
        ['type' => 'file_upload'],
    ]);
    $seed['attempt']->update(['expires_at' => now()->subMinute()]);

    ExamAttemptAnswer::create([
        'exam_attempt_id' => $seed['attempt']->id,
        'exam_attempt_question_id' => $seed['attempt']->attemptQuestions()->first()->id,
        'selected_answer_ids' => [$seed['questions'][0]['answers'][0]->id],
    ]);

    submitAnswers($user, $seed['exam'], ['auto_submit' => '1']);

    $fileAnswer = StudentAnswer::where('question_id', $seed['questions'][1]['question']->id)->first();

    expect($fileAnswer)->not->toBeNull()
        ->and($fileAnswer->is_graded)->toBeFalse()
        ->and($fileAnswer->file_path)->toBeNull()
        ->and($fileAnswer->admin_feedback)->toBe('File not submitted before time limit.');
});

it('still accepts a normal on-time submission unaffected by expiry logic', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [1]],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 1],
    ]);

    expect(ExamResult::first()->score)->toBe(1);
});

it('delays the client auto-submit past the server grace period so it is not rejected as incomplete', function () {
    config(['exam.grace_period_seconds' => 45]);

    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['exam']->update(['time_limit_minutes' => 30]);

    $html = test()->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    // Regression guard: the auto-submit used to fire ~1.5s after the countdown
    // hit zero, well inside ExamAttempt::isExpired()'s grace period. The server
    // then still required every question to be answered, failed validation on
    // whatever wasn't, and bounced back to this same page - which reran the
    // same too-early auto-submit and looped. The real submit must wait out the
    // configured grace period (plus a buffer) so the server already agrees the
    // attempt is over by the time the request lands.
    // The wait itself is asserted in tests/js/exam/timer.test.js ("waits for the
    // grace countdown even when saves finish instantly"). What has to hold on
    // this side is that the configured grace period actually reaches the client
    // rather than the timer falling back to a hardcoded default.
    expect($html)->toContain('"gracePeriodSeconds":45')
        ->and($html)->toContain('id="autoSubmitCountdown">');
});

it('gates the auto-submit on every answer finishing its fast, bounded save attempt', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 4, 'correct' => [0]],
    ]);
    $seed['exam']->update(['time_limit_minutes' => 30]);

    $html = test()->actingAs($user)
        ->get(route('student.exam', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    // Regression guard: the grace-period wait alone only stops the SUBMIT
    // from arriving too early - it does nothing to guarantee every answer
    // actually reached the server first. Before this, the last-ditch save
    // was a fire-and-forget flushAutosaveQueue() call racing an unrelated
    // fixed timer, so a slow save (or one still silently retrying in the
    // background, never even in the queue) could get cut off and lost. The
    // submit must instead wait on finalizeAllAnswers() to settle, and that
    // function's own retries must be tightly bounded (timeout + short,
    // capped backoff) so a dead connection can't stall it past the visible
    // countdown either.
    // Both halves of that gate now have executing tests: "waits for the saves
    // even when they outlast the countdown" (timer.test.js) and "uses two
    // attempts 300ms apart when finalising for auto-submit" (autosave.test.js).
    // This asserts the page is wired for a timed run at all, which is the
    // precondition those tests assume.
    expect($html)->toContain('"timed":true')
        ->and($html)->toContain('id="auto_submit"');
});
