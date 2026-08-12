<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamQuestionBank;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\ExamGenerationService;

/**
 * A bank holding $count questions per difficulty, so any quota up to $count is
 * satisfiable and the generator has room to shuffle rather than being forced
 * into one possible order.
 */
function seedBank(string $name, int $count = 6): QuestionBank
{
    $bank = QuestionBank::factory()->create(['name' => $name]);

    foreach (['easy', 'medium', 'hard'] as $difficulty) {
        foreach (range(1, $count) as $i) {
            $question = Question::factory()->create([
                'question_bank_id' => $bank->id,
                'question_type' => 'single',
                'difficulty' => $difficulty,
            ]);

            foreach (range(0, 3) as $position) {
                Answer::create([
                    'question_id' => $question->id,
                    'answer_text' => "Option {$position}",
                    'is_correct' => $position === 0,
                ]);
            }
        }
    }

    return $bank;
}

function attachBankToExam(Exam $exam, QuestionBank $bank, int $sortOrder, array $quotas = []): ExamQuestionBank
{
    return ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => $quotas['easy'] ?? 2,
        'quota_medium' => $quotas['medium'] ?? 2,
        'quota_hard' => $quotas['hard'] ?? 1,
        'sort_order' => $sortOrder,
    ]);
}

/** The bank name behind each question, in the order the student will see them. */
function generatedBankSequence(Exam $exam, User $user): array
{
    $attempt = app(ExamGenerationService::class)->generate($exam, $user);

    return $attempt->attemptQuestions()
        ->with('question.questionBank')
        ->get()
        ->map(fn ($aq) => $aq->question->questionBank->name)
        ->all();
}

it('lays banks out in the order they were attached to the exam', function () {
    $exam = Exam::factory()->create();
    attachBankToExam($exam, seedBank('English'), 0);
    attachBankToExam($exam, seedBank('Logic'), 1);
    attachBankToExam($exam, seedBank('Maths'), 2);

    $sequence = generatedBankSequence($exam, User::factory()->create());

    // 5 questions per bank (2 easy + 2 medium + 1 hard), in attachment order.
    expect($sequence)->toBe(array_merge(
        array_fill(0, 5, 'English'),
        array_fill(0, 5, 'Logic'),
        array_fill(0, 5, 'Maths'),
    ));
});

it('keeps each bank as one unbroken block, never interleaved', function () {
    $exam = Exam::factory()->create();
    attachBankToExam($exam, seedBank('English'), 0);
    attachBankToExam($exam, seedBank('Logic'), 1);

    // Ten independent students: interleaving would have to fail for all of them
    // to slip through, which a shuffle across banks would not do.
    foreach (range(1, 10) as $i) {
        $sequence = generatedBankSequence($exam, User::factory()->create());
        $blocks = array_values(array_unique($sequence));

        expect($blocks)->toBe(['English', 'Logic']);
        expect(count($sequence))->toBe(10);
    }
});

it('follows attachment order even when it differs from bank id order', function () {
    $exam = Exam::factory()->create();
    $english = seedBank('English');
    $logic = seedBank('Logic');

    // Logic was created second but attached first.
    attachBankToExam($exam, $logic, 0);
    attachBankToExam($exam, $english, 1);

    $sequence = generatedBankSequence($exam, User::factory()->create());

    expect(array_values(array_unique($sequence)))->toBe(['Logic', 'English']);
});

it('shuffles questions inside a bank instead of running easy to hard', function () {
    $exam = Exam::factory()->create();
    attachBankToExam($exam, seedBank('English'), 0, ['easy' => 3, 'medium' => 3, 'hard' => 3]);

    $difficultyOrders = [];

    foreach (range(1, 12) as $i) {
        $attempt = app(ExamGenerationService::class)->generate($exam, User::factory()->create());

        $difficultyOrders[] = implode(',', $attempt->attemptQuestions()
            ->with('question')
            ->get()
            ->map(fn ($aq) => $aq->question->difficulty)
            ->all());
    }

    // Unshuffled generation produces the identical easy,easy,easy,medium,... string
    // every time; a within-bank shuffle makes repeats vanishingly unlikely.
    expect(count(array_unique($difficultyOrders)))->toBeGreaterThan(1);
});

it('gives two students different question orders within the same bank', function () {
    $exam = Exam::factory()->create();
    attachBankToExam($exam, seedBank('English', 12), 0, ['easy' => 6, 'medium' => 0, 'hard' => 0]);

    $orders = [];

    foreach (range(1, 12) as $i) {
        $attempt = app(ExamGenerationService::class)->generate($exam, User::factory()->create());
        $orders[] = implode(',', $attempt->attemptQuestions()->pluck('question_id')->all());
    }

    expect(count(array_unique($orders)))->toBeGreaterThan(1);
});

it('numbers display_order contiguously from zero', function () {
    $exam = Exam::factory()->create();
    attachBankToExam($exam, seedBank('English'), 0);
    attachBankToExam($exam, seedBank('Logic'), 1);

    $attempt = app(ExamGenerationService::class)->generate($exam, User::factory()->create());

    expect($attempt->attemptQuestions()->pluck('display_order')->all())->toBe(range(0, 9));
});

it('still refuses to generate when a bank cannot meet its quota', function () {
    $exam = Exam::factory()->create();
    attachBankToExam($exam, seedBank('English'), 0);
    attachBankToExam($exam, seedBank('Logic', 1), 1, ['easy' => 5, 'medium' => 0, 'hard' => 0]);

    expect(fn () => app(ExamGenerationService::class)->generate($exam, User::factory()->create()))
        ->toThrow(RuntimeException::class, "Bank 'Logic' has only 1 easy question(s)");
});
