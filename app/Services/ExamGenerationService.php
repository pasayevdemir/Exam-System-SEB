<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Services;

use App\Exceptions\ConcurrentExamAttemptException;
use App\Exceptions\ExamClosedException;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamGenerationService
{
    /**
     * Randomly generate and pin a student's question set for an exam, per the
     * exam's per-bank/per-difficulty quotas. Throws if any bank's pool can't
     * satisfy its configured quota.
     *
     * Banks are laid out in the order they were attached to the exam, and a
     * bank's questions are shuffled only within its own block. So every student
     * sits the same subjects in the same sequence - which is what makes the
     * paper readable and lets a student budget time per subject - while still
     * getting a different question order inside each subject, so neighbours
     * cannot read answers off each other by position.
     */
    public function generate(Exam $exam, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user) {
            // Serialise generation per student. The controller checks for an open
            // attempt too, but on its own that check loses a double-click race:
            // both requests read "none open" before either inserts. Taking the
            // student's own row first makes the second request wait and then see
            // the first one's attempt.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            // The exam row too, and then re-read is_active under the lock. The
            // controller checked it, but outside any transaction: without this,
            // a Start that lands exactly as an admin deactivates the exam slips
            // past both guards and strands an attempt on a closed exam. Ordering
            // against AdminController::toggleExamStatus is now decided by the
            // lock - whichever transaction gets the row first wins outright.
            if (! DB::table('exams')->where('id', $exam->id)->lockForUpdate()->value('is_active')) {
                throw new ExamClosedException($exam);
            }

            $openAttempt = ExamAttempt::inProgress()
                ->where('user_id', $user->id)
                ->where('exam_id', '!=', $exam->id)
                ->first();

            if ($openAttempt) {
                throw new ConcurrentExamAttemptException($openAttempt);
            }

            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'expires_at' => $exam->time_limit_minutes
                    ? now()->addMinutes($exam->time_limit_minutes)
                    : null,
            ]);

            $order = 0;
            $totalWeight = 0.0;

            // orderBy('id') is the tiebreaker: sort_order is assigned from a
            // count at attach time, so two rows can share a value if an earlier
            // bank was detached, and the bank sequence has to stay stable anyway.
            $bankAssignments = $exam->examQuestionBanks()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($bankAssignments as $eqb) {
                $quotas = [
                    'easy' => $eqb->quota_easy,
                    'medium' => $eqb->quota_medium,
                    'hard' => $eqb->quota_hard,
                ];

                $bankQuestions = collect();

                foreach ($quotas as $difficulty => $quota) {
                    if ($quota === 0) {
                        continue;
                    }

                    $pool = Question::where('question_bank_id', $eqb->question_bank_id)
                        ->where('difficulty', $difficulty)
                        ->inRandomOrder()
                        ->limit($quota)
                        ->get();

                    if ($pool->count() < $quota) {
                        $bankName = $eqb->questionBank->name;
                        throw new \RuntimeException(
                            "Bank '{$bankName}' has only {$pool->count()} {$difficulty} question(s) but exam requires {$quota}."
                        );
                    }

                    $bankQuestions = $bankQuestions->concat($pool);
                }

                // Shuffle inside the bank only. Without this the block would run
                // easy-then-medium-then-hard for everyone, which both telegraphs
                // each question's weight and gives every student an identical order.
                foreach ($bankQuestions->shuffle() as $question) {
                    $answerOrder = $question->isMCQ()
                        ? $question->answers()->pluck('id')->shuffle()->values()->all()
                        : null;

                    ExamAttemptQuestion::create([
                        'exam_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'display_order' => $order++,
                        'weight_at_generation' => $question->getWeight(),
                        'answer_display_order' => $answerOrder,
                    ]);
                    $totalWeight += $question->getWeight();
                }
            }

            $attempt->update(['target_weight' => $totalWeight]);

            return $attempt;
        });
    }
}
