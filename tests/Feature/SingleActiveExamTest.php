<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Exceptions\ConcurrentExamAttemptException;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestionBank;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\ExamGenerationService;

beforeEach(function () {
    config(['seb.required' => false]);
});

/** An active exam with one bank attached and enough easy questions to generate. */
function seedStartableExam(string $name, int $quota = 2): Exam
{
    $exam = Exam::factory()->create(['exam_name' => $name, 'is_active' => true]);
    $bank = QuestionBank::factory()->create(['name' => "{$name} Bank"]);

    foreach (range(1, $quota + 2) as $i) {
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'question_type' => 'single',
            'difficulty' => 'easy',
        ]);

        foreach (range(0, 3) as $position) {
            Answer::create([
                'question_id' => $question->id,
                'answer_text' => "Option {$position}",
                'is_correct' => $position === 0,
            ]);
        }
    }

    ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => $quota,
        'quota_medium' => 0,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    return $exam;
}

function openAttemptFor(User $user, Exam $exam, ?int $minutesLeft = 30): ExamAttempt
{
    return ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
        'expires_at' => $minutesLeft === null ? null : now()->addMinutes($minutesLeft),
    ]);
}

// ── The guard ──────────────────────────────────────────────────────────────

it('blocks starting a second exam while one is in progress', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    openAttemptFor($user, $first);

    $this->actingAs($user)
        ->get(route('student.exam', $second->id))
        ->assertRedirect(route('student.exams'))
        ->assertSessionHas('error');

    expect(ExamAttempt::where('user_id', $user->id)->count())->toBe(1);
});

it('names the exam the student has to finish first', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('Mathematics Midterm');
    $second = seedStartableExam('Second');

    openAttemptFor($user, $first);

    $response = $this->actingAs($user)->get(route('student.exam', $second->id));

    expect($response->getSession()->get('error'))->toContain('Mathematics Midterm');
});

it('still lets the student resume the exam they already started', function () {
    $user = User::factory()->create();
    $exam = seedStartableExam('First');

    $attempt = app(ExamGenerationService::class)->generate($exam, $user);

    $this->actingAs($user)
        ->get(route('student.exam', $exam->id))
        ->assertOk();

    expect(ExamAttempt::where('user_id', $user->id)->count())->toBe(1)
        ->and(ExamAttempt::where('user_id', $user->id)->first()->id)->toBe($attempt->id);
});

it('unlocks other exams once the open one is submitted', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    $attempt = openAttemptFor($user, $first);
    $attempt->update(['completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('student.exam', $second->id))
        ->assertOk();

    expect(ExamAttempt::where('user_id', $user->id)->where('exam_id', $second->id)->exists())->toBeTrue();
});

it('does not let an expired abandoned attempt lock a student out forever', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    // Abandoned and well past its expiry plus the grace period.
    openAttemptFor($user, $first, minutesLeft: -10);

    $this->actingAs($user)
        ->get(route('student.exam', $second->id))
        ->assertOk();
});

it('keeps blocking while the open attempt is inside its grace period', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    config(['exam.grace_period_seconds' => 120]);
    $attempt = openAttemptFor($user, $first);
    $attempt->update(['expires_at' => now()->subSeconds(30)]);

    $this->actingAs($user)
        ->get(route('student.exam', $second->id))
        ->assertRedirect(route('student.exams'));
});

it('ignores a superseded attempt when deciding what is open', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    openAttemptFor($user, $first)->update(['superseded_at' => now()]);

    $this->actingAs($user)
        ->get(route('student.exam', $second->id))
        ->assertOk();
});

it('does not let one student block another', function () {
    $busy = User::factory()->create();
    $free = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    openAttemptFor($busy, $first);

    $this->actingAs($free)
        ->get(route('student.exam', $second->id))
        ->assertOk();
});

it('refuses generation at the service layer, not just the controller', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    $second = seedStartableExam('Second');

    openAttemptFor($user, $first);

    expect(fn () => app(ExamGenerationService::class)->generate($second, $user))
        ->toThrow(ConcurrentExamAttemptException::class);

    expect(ExamAttempt::where('exam_id', $second->id)->count())->toBe(0);
});

// ── The cards ──────────────────────────────────────────────────────────────

it('renders each exam as a card with its details', function () {
    $user = User::factory()->create();
    $exam = seedStartableExam('Mathematics Midterm', quota: 3);
    $exam->update(['description' => 'Covers chapters 1-4.', 'time_limit_minutes' => 45]);

    $this->actingAs($user)
        ->get(route('student.exams'))
        ->assertOk()
        ->assertSee('ps-exam-card', false)
        ->assertSee('Mathematics Midterm')
        ->assertSee('Covers chapters 1-4.')
        ->assertSee('3 questions')
        ->assertSee('45 minutes');
});

it('says so when an exam has no time limit', function () {
    $user = User::factory()->create();
    seedStartableExam('Untimed')->update(['time_limit_minutes' => null]);

    $this->actingAs($user)
        ->get(route('student.exams'))
        ->assertOk()
        ->assertSee('No time limit');
});

it('flags an exam that needs an entry password', function () {
    $user = User::factory()->create();
    seedStartableExam('Guarded')->update(['entry_password' => 'hunter2']);

    $this->actingAs($user)
        ->get(route('student.exams'))
        ->assertOk()
        ->assertSee('Entry password required');
});

it('offers Start on every card when nothing is in progress', function () {
    $user = User::factory()->create();
    seedStartableExam('First');
    seedStartableExam('Second');

    $this->actingAs($user)
        ->get(route('student.exams'))
        ->assertOk()
        ->assertDontSee('Resume Exam')
        ->assertDontSee('Finish your open exam first');
});

it('shows Resume on the open exam and locks the rest', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    seedStartableExam('Second');

    openAttemptFor($user, $first);

    $this->actingAs($user)
        ->get(route('student.exams'))
        ->assertOk()
        ->assertSee('Resume Exam')
        ->assertSee('In progress')
        ->assertSee('Finish your open exam first')
        ->assertSee('is still in progress');
});

it('drops a completed exam off the list entirely', function () {
    $user = User::factory()->create();
    $first = seedStartableExam('First');
    seedStartableExam('Second');

    openAttemptFor($user, $first)->update(['completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('student.exams'))
        ->assertOk()
        ->assertDontSee('First')
        ->assertSee('Second');
});
