<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

/**
 * How long the sitting took, in the admin results table.
 *
 * The cell under each submission date now reports the minutes the student spent
 * and, on a timed exam, how much of the allowed time that was. The figure comes
 * from ExamAttempt::durationMinutes() - started_at to completed_at - so these
 * tests pin both that method and the three shapes the cell can take: measured
 * against a limit, on its own for an untimed exam, and absent for a result whose
 * attempt was cleared.
 */

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\User;

/**
 * A finished result with the sitting times set directly, so duration does not
 * depend on how long the test itself took to run.
 *
 * @return array{exam: Exam, result: ExamResult}
 */
function seedTimedResult(?int $limitMinutes, ?int $tookMinutes): array
{
    $exam = Exam::factory()->create(['time_limit_minutes' => $limitMinutes]);
    $user = User::factory()->create();

    $attempt = $tookMinutes === null ? null : ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now()->subMinutes($tookMinutes),
        'completed_at' => now(),
    ]);

    $result = ExamResult::create([
        'exam_id' => $exam->id,
        'exam_attempt_id' => $attempt?->id,
        'user_id' => $user->id,
        'student_id' => 'S1',
        'index_no' => 'I1',
        'total_questions' => 50,
        'correct_answers' => 20,
        'score' => 30.5,
        'submitted_at' => now(),
    ]);

    return ['exam' => $exam, 'result' => $result];
}

describe('ExamAttempt::durationMinutes', function () {
    it('measures start to submission', function () {
        $attempt = new ExamAttempt([
            'started_at' => now()->subMinutes(45),
            'completed_at' => now(),
        ]);

        expect($attempt->durationMinutes())->toBe(45);
    });

    it('is null while the attempt is still open', function () {
        $attempt = new ExamAttempt(['started_at' => now(), 'completed_at' => null]);

        expect($attempt->durationMinutes())->toBeNull();
    });
});

describe('the results table duration cell', function () {
    it('shows minutes used against the limit, with a bar, on a timed exam', function () {
        $seed = seedTimedResult(limitMinutes: 60, tookMinutes: 45);

        $html = actingAsAdmin()
            ->get(route('admin.exam-results', $seed['exam']->id))
            ->assertOk()
            ->getContent();

        expect($html)->toContain('45 dəq / 60')
            ->and($html)->toContain('ps-dur-track')
            ->and($html)->toContain('ps-bank-bar--good');
    });

    it('turns the bar amber as the clock runs out', function () {
        $seed = seedTimedResult(limitMinutes: 60, tookMinutes: 58);

        $html = actingAsAdmin()
            ->get(route('admin.exam-results', $seed['exam']->id))
            ->assertOk()
            ->getContent();

        expect($html)->toContain('58 dəq / 60')
            ->and($html)->toContain('ps-bank-bar--mid');
    });

    it('shows a bare duration and no bar for an untimed exam', function () {
        $seed = seedTimedResult(limitMinutes: null, tookMinutes: 45);

        $html = actingAsAdmin()
            ->get(route('admin.exam-results', $seed['exam']->id))
            ->assertOk()
            ->getContent();

        expect($html)->toContain('45 dəq')
            ->and($html)->not->toContain('ps-dur-track');
    });

    it('shows a dash when the attempt was cleared', function () {
        $seed = seedTimedResult(limitMinutes: 60, tookMinutes: null);

        $html = actingAsAdmin()
            ->get(route('admin.exam-results', $seed['exam']->id))
            ->assertOk()
            ->getContent();

        // Anchored to the cell's own markup: an em dash also lives in the
        // layout footer, so a bare toContain('—') would pass without the cell.
        expect($html)->toContain('ps-pct-meta">—')
            ->and($html)->not->toContain('ps-dur-track');
    });
});
