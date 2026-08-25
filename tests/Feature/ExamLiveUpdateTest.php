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
use App\Models\ExamQuestionBank;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;

/**
 * The student list pages are server-rendered, so an admin activating an exam is
 * invisible until the student reloads. Two halves close that gap and both are
 * covered here:
 *
 *  - a deactivation guard, so an exam cannot be pulled out from under anyone
 *    who is already sitting it;
 *  - /exams/state and /my-results/state, which hand an open page the rendered
 *    fragment when — and only when — the caller's hash has gone stale.
 */
function actingAsAdmin()
{
    return test()->withSession(['admin_logged_in' => true]);
}

/**
 * An attempt in whatever state the caller needs, on its own active exam.
 *
 * @return array{user: User, exam: Exam, attempt: ExamAttempt}
 */
function seedAttemptInState(array $attributes = []): array
{
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true]);

    $attempt = ExamAttempt::create(array_merge([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ], $attributes));

    return compact('user', 'exam', 'attempt');
}

function toggleStatus(Exam $exam)
{
    return actingAsAdmin()->post(route('admin.toggle-status', $exam->id));
}

/* -------------------------------------------------------------------------- */
/* An exam being sat cannot be deactivated */
/* -------------------------------------------------------------------------- */

it('refuses to deactivate an exam a student is sitting', function () {
    ['exam' => $exam] = seedAttemptInState();

    toggleStatus($exam)->assertRedirect(route('admin.dashboard'))->assertSessionHas('error');

    expect($exam->fresh()->is_active)->toBeTrue();
});

it('names the number of students sitting in the refusal', function () {
    $exam = Exam::factory()->create(['is_active' => true, 'exam_name' => 'Algorithms Midterm']);

    foreach (range(1, 3) as $i) {
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => User::factory()->create()->id,
            'started_at' => now(),
        ]);
    }

    toggleStatus($exam);

    expect(session('error'))->toContain('3 student(s)')->toContain('Algorithms Midterm');
});

it('allows deactivation once the attempt is submitted', function () {
    ['exam' => $exam] = seedAttemptInState(['completed_at' => now()]);

    toggleStatus($exam)->assertSessionHas('success');

    expect($exam->fresh()->is_active)->toBeFalse();
});

it('allows deactivation once a timed attempt has run past its grace period', function () {
    config(['exam.grace_period_seconds' => 30]);

    // Abandoned rather than submitted: inProgress()'s expiry arm has to release
    // it, or walking away from an exam would lock an admin out of it for good.
    ['exam' => $exam] = seedAttemptInState(['expires_at' => now()->subMinutes(5)]);

    toggleStatus($exam)->assertSessionHas('success');

    expect($exam->fresh()->is_active)->toBeFalse();
});

it('still refuses while a timed attempt is inside its grace period', function () {
    config(['exam.grace_period_seconds' => 30]);

    ['exam' => $exam] = seedAttemptInState(['expires_at' => now()->subSeconds(5)]);

    toggleStatus($exam)->assertSessionHas('error');

    expect($exam->fresh()->is_active)->toBeTrue();
});

it('allows deactivation once the attempt has been superseded for a retake', function () {
    ['exam' => $exam] = seedAttemptInState([
        'completed_at' => now(),
        'superseded_at' => now(),
    ]);

    toggleStatus($exam)->assertSessionHas('success');

    expect($exam->fresh()->is_active)->toBeFalse();
});

it('always allows activation, even with an attempt open', function () {
    // An open attempt on an inactive exam is exactly the state an admin needs to
    // be able to fix, so the guard must not read as symmetric.
    ['exam' => $exam] = seedAttemptInState();
    $exam->update(['is_active' => false]);

    toggleStatus($exam)->assertSessionHas('success');

    expect($exam->fresh()->is_active)->toBeTrue();
});

it('refuses a student a fresh attempt on an exam deactivated under the lock', function () {
    // The controller checks is_active outside any transaction; generation
    // re-reads it under the row lock. Simulated here by deactivating after the
    // session was established but before generation would run.
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => false]);

    test()->actingAs($user)
        ->get(route('student.exam', $exam->id))
        ->assertRedirect(route('student.exams'));

    expect(ExamAttempt::where('exam_id', $exam->id)->count())->toBe(0);
});

/* -------------------------------------------------------------------------- */
/* The exams-list poll endpoint */
/* -------------------------------------------------------------------------- */

/**
 * withSession() leaves admin_logged_in on the shared test session, and
 * StudentMiddleware bounces any student request carrying it. Flushing first lets
 * a test drive the admin side and then check what the student now sees, which is
 * the whole point of these cases.
 */
function examsState(User $user, ?string $version = null)
{
    test()->flushSession();

    return test()->actingAs($user)
        ->getJson(route('student.exams-state', $version === null ? [] : ['v' => $version]));
}

it('reports no change when the caller already has the current hash', function () {
    $user = User::factory()->create();
    Exam::factory()->create(['is_active' => true]);

    $version = examsState($user)->assertOk()->json('v');

    examsState($user, $version)->assertOk()->assertExactJson(['changed' => false]);
});

it('sends the new fragment after an admin activates an exam', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => false, 'exam_name' => 'Discrete Maths Final']);

    $stale = examsState($user)->assertOk()->json('v');

    toggleStatus($exam)->assertSessionHas('success');

    $response = examsState($user, $stale)->assertOk();

    expect($response->json('changed'))->toBeTrue();
    expect($response->json('v'))->not->toBe($stale);
    expect($response->json('html'))->toContain('Discrete Maths Final');
});

it('drops an exam from the fragment after an admin deactivates it', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true, 'exam_name' => 'Linear Algebra Quiz']);

    $stale = examsState($user)->assertOk()->json('v');
    expect(examsState($user)->json('html'))->toContain('Linear Algebra Quiz');

    toggleStatus($exam)->assertSessionHas('success');

    expect(examsState($user, $stale)->json('html'))
        ->not->toContain('Linear Algebra Quiz');
});

it('reflects a renamed exam without any status change', function () {
    // The hash is taken over the rendered fragment, so edits that never touch
    // is_active still reach the student. This is the case a status-only
    // fingerprint would silently miss.
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true, 'exam_name' => 'Original Title']);

    $stale = examsState($user)->assertOk()->json('v');

    $exam->update(['exam_name' => 'Renamed Title']);

    $html = examsState($user, $stale)->assertOk()->json('html');

    expect($html)->toContain('Renamed Title')->not->toContain('Original Title');
});

it('reflects a changed question count when a bank is attached', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true]);

    $stale = examsState($user)->assertOk()->json('v');

    ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => QuestionBank::factory()->create()->id,
        'quota_easy' => 4,
        'quota_medium' => 3,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    expect(examsState($user, $stale)->json('html'))->toContain('7 questions');
});

it('brings an exam back into the fragment once a retake is allowed', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true, 'exam_name' => 'Resittable Exam']);

    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now()->subHour(),
        'completed_at' => now()->subMinutes(30),
    ]);

    // Completed, so it is filtered out of the list entirely.
    expect(examsState($user)->json('html'))->not->toContain('Resittable Exam');

    $attempt->update(['superseded_at' => now()]);

    expect(examsState($user)->json('html'))->toContain('Resittable Exam');
});

it('is scoped to the calling student', function () {
    // openAttempt drives the Resume/locked card states, so one student's open
    // attempt must never leak into another's fragment.
    $sitting = User::factory()->create();
    $exam = Exam::factory()->create(['is_active' => true, 'exam_name' => 'Shared Exam']);

    ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $sitting->id,
        'started_at' => now(),
    ]);

    $bystander = User::factory()->create();

    expect(examsState($sitting)->json('html'))->toContain('is still in progress');
    expect(examsState($bystander)->json('html'))->not->toContain('is still in progress');
});

/* -------------------------------------------------------------------------- */
/* The results poll endpoint */
/* -------------------------------------------------------------------------- */

it('replaces grading-pending with a score once an admin grades a submission', function () {
    $seed = seedFinishedAttempt();
    $user = $seed['user'];

    StudentAnswer::where('exam_result_id', $seed['result']->id)->delete();

    $pending = StudentAnswer::create([
        'exam_result_id' => $seed['result']->id,
        'question_id' => $seed['question']->id,
        'file_path' => 'submissions/essay.pdf',
        'is_graded' => false,
    ]);

    $stale = test()->actingAs($user)
        ->getJson(route('student.my-results-state'))
        ->assertOk();

    expect($stale->json('html'))->toContain('Grading Pending');

    $pending->update(['is_graded' => true, 'manual_score' => 80]);
    $seed['result']->recalculateScore();

    $fresh = test()->actingAs($user)
        ->getJson(route('student.my-results-state', ['v' => $stale->json('v')]))
        ->assertOk();

    expect($fresh->json('changed'))->toBeTrue();
    expect($fresh->json('html'))->not->toContain('Grading Pending');
});

it('reports no change on the results fragment when nothing was graded', function () {
    $seed = seedFinishedAttempt();

    $version = test()->actingAs($seed['user'])
        ->getJson(route('student.my-results-state'))
        ->assertOk()
        ->json('v');

    test()->actingAs($seed['user'])
        ->getJson(route('student.my-results-state', ['v' => $version]))
        ->assertOk()
        ->assertExactJson(['changed' => false]);
});

it('does not let the results state route be swallowed by the result-id route', function () {
    // /my-results/state and /my-results/{examResult} share a prefix; registered
    // the wrong way round, 'state' binds as an id and 404s.
    $seed = seedFinishedAttempt();

    test()->actingAs($seed['user'])
        ->getJson(route('student.my-results-state'))
        ->assertOk()
        ->assertJsonStructure(['changed', 'v', 'html']);
});

/* -------------------------------------------------------------------------- */
/* The page and the endpoint must agree on the hash */
/* -------------------------------------------------------------------------- */

/**
 * The data-v the page was rendered with. If this ever stops matching what the
 * endpoint computes, the first poll swaps in markup identical to what is already
 * on screen — cheap, but it means the two render paths have drifted apart.
 */
function renderedVersion(string $html, string $containerId): string
{
    // Anything may sit between the id and the hash - the live-poll endpoint URL
    // moved onto this element when the page script became a module - so the
    // pattern reads to the end of the tag rather than to the next attribute.
    $pattern = '/id="'.$containerId.'"[^>]*?data-v="([0-9a-f]{40})"/s';

    expect($html)->toMatch($pattern);

    preg_match($pattern, $html, $matches);

    return $matches[1];
}

it('hands the exams page a hash the endpoint immediately agrees with', function () {
    $user = User::factory()->create();
    Exam::factory()->count(2)->create(['is_active' => true]);

    $page = test()->actingAs($user)->get(route('student.exams'))->assertOk();

    $version = renderedVersion($page->getContent(), 'examListLive');

    examsState($user, $version)->assertOk()->assertExactJson(['changed' => false]);
});

it('hands the results page a hash the endpoint immediately agrees with', function () {
    $seed = seedFinishedAttempt();

    $page = test()->actingAs($seed['user'])->get(route('student.my-results'))->assertOk();

    $version = renderedVersion($page->getContent(), 'resultListLive');

    test()->actingAs($seed['user'])
        ->getJson(route('student.my-results-state', ['v' => $version]))
        ->assertOk()
        ->assertExactJson(['changed' => false]);
});

/* -------------------------------------------------------------------------- */
/* Both endpoints sit behind the student guard */
/* -------------------------------------------------------------------------- */

it('redirects a guest away from the poll endpoints', function (string $route) {
    test()->get(route($route))->assertRedirect(route('student.login'));
})->with(['student.exams-state', 'student.my-results-state']);

/* -------------------------------------------------------------------------- */
/* Keep-alive carries the exam's active flag */
/* -------------------------------------------------------------------------- */

it('reports the exam as active on keep-alive while it is being sat', function () {
    ['user' => $user] = seedAttemptInState();

    test()->actingAs($user)
        ->getJson(route('student.keep-alive'))
        ->assertOk()
        ->assertJson(['exam_active' => true]);
});

it('reports the exam as inactive on keep-alive if the guard was bypassed', function () {
    // Written straight to the column, since toggleExamStatus would refuse. This
    // is the only way the banner can ever appear, which is the point of testing it.
    ['user' => $user, 'exam' => $exam] = seedAttemptInState();
    Exam::where('id', $exam->id)->update(['is_active' => false]);

    test()->actingAs($user)
        ->getJson(route('student.keep-alive'))
        ->assertOk()
        ->assertJson(['exam_active' => false]);
});

it('reports a null active flag when there is no attempt to speak of', function () {
    $user = User::factory()->create();

    test()->actingAs($user)
        ->getJson(route('student.keep-alive'))
        ->assertOk()
        ->assertJson(['exam_active' => null]);
});
