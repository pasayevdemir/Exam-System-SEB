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
 * A mark is a sum of weights, not a count of ticks.
 *
 * A hard question is worth two easy ones, so "17 correct" and "24.5 marks" are
 * different numbers and every place that reports one has to say which. What is
 * covered here is that they agree: the score written at submission, the score
 * rewritten after a file is graded by hand, and the per-bank breakdown the
 * results page draws all read the same weight_at_generation off the attempt.
 */

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\User;

it('stores the summed weight of the right answers, not a count of them', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 1.0],
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 1.5],
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 2.0],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [
            $seed['questions'][0]['question']->id => 1,  // wrong
            $seed['questions'][1]['question']->id => 0,  // right, 1.5
            $seed['questions'][2]['question']->id => 0,  // right, 2.0
        ],
    ]);

    $result = ExamResult::first();

    expect((float) $result->score)->toBe(3.5)
        ->and($result->correct_answers)->toBe(2)
        ->and($result->total_questions)->toBe(3)
        ->and($result->maxScore())->toBe(4.5);
});

it('shows the student their mark out of the marks available', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 2.0],
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 1.5],
    ]);

    $html = submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ])->assertOk()->getContent();

    // 2 of 3.5, rather than "1" or "1/2".
    expect($html)->toContain('2<span class="text-muted">/3.5</span>');
});

it('adds a hand-graded file answer at the weight its question was served with', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 1.5],
        ['type' => 'file_upload', 'weight' => 2.0],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    $result = ExamResult::first();
    expect((float) $result->score)->toBe(1.5);

    // The submit path stores a row with no file for a skipped upload; make it
    // look like one that arrived, so the grading endpoint has something to mark.
    $fileAnswer = StudentAnswer::where('question_id', $seed['questions'][1]['question']->id)->first();
    $fileAnswer->update(['file_path' => 'exam_submissions/proof.pdf']);

    actingAsAdmin()
        ->put(route('admin.grade-file-submission', $fileAnswer->id), ['manual_score' => 75]);

    $result->refresh();

    expect((float) $result->score)->toBe(3.5)
        ->and($result->correct_answers)->toBe(2);
});

it('leaves a file answer graded below the pass mark unscored', function () {
    $user = User::factory()->create();
    $seed = seedAttempt($user, [
        ['type' => 'single', 'options' => 3, 'correct' => [0], 'weight' => 1.0],
        ['type' => 'file_upload', 'weight' => 2.0],
    ]);

    submitAnswers($user, $seed['exam'], [
        'answers' => [$seed['questions'][0]['question']->id => 0],
    ]);

    $fileAnswer = StudentAnswer::where('question_id', $seed['questions'][1]['question']->id)->first();
    $fileAnswer->update(['file_path' => 'exam_submissions/proof.pdf']);

    actingAsAdmin()
        ->put(route('admin.grade-file-submission', $fileAnswer->id), ['manual_score' => 49]);

    expect((float) ExamResult::first()->score)->toBe(1.0);
});

/* -------------------------------------------------------------------------- */
/*  The per-bank breakdown */
/* -------------------------------------------------------------------------- */

/**
 * A four-question paper drawn from two named banks, so a breakdown has
 * something to group by. Every question is single-choice with its first option
 * correct, which makes an answer of position 0 right and anything else wrong.
 *
 * @return array{exam: Exam, questions: array<int, Question>}
 */
function seedTwoBankPaper(User $user): array
{
    $exam = Exam::factory()->create();
    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    $plan = [
        ['bank' => 'Algebra', 'weight' => 1.0],
        ['bank' => 'Algebra', 'weight' => 2.0],
        ['bank' => 'Geometry', 'weight' => 1.5],
        ['bank' => 'Geometry', 'weight' => 1.5],
    ];

    $banks = collect($plan)->pluck('bank')->unique()
        ->mapWithKeys(fn ($name) => [$name => QuestionBank::factory()->create(['name' => $name])]);

    $questions = [];
    $totalWeight = 0.0;

    foreach ($plan as $order => $spec) {
        $question = Question::factory()->create([
            'question_bank_id' => $banks[$spec['bank']]->id,
            'question_type' => 'single',
        ]);

        $answers = collect(['right', 'wrong'])->map(fn ($text, $i) => Answer::create([
            'question_id' => $question->id,
            'answer_text' => $text,
            'is_correct' => $i === 0,
        ]));

        ExamAttemptQuestion::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'display_order' => $order,
            'weight_at_generation' => $spec['weight'],
            'answer_display_order' => $answers->pluck('id')->all(),
        ]);

        $totalWeight += $spec['weight'];
        $questions[] = $question;
    }

    $attempt->update(['target_weight' => $totalWeight]);

    return ['exam' => $exam, 'questions' => $questions];
}

it('reports each bank as marks earned out of marks offered', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankPaper($user);
    [$algebraEasy, $algebraHard, $geometryOne, $geometryTwo] = $seed['questions'];

    submitAnswers($user, $seed['exam'], [
        'answers' => [
            $algebraEasy->id => 0,   // right, 1.0 of Algebra's 3.0
            $algebraHard->id => 1,   // wrong
            $geometryOne->id => 0,   // right, 1.5 of Geometry's 3.0
            // $geometryTwo deliberately left blank
        ],
    ]);

    $breakdown = ExamResult::first()->getQuestionBankBreakdown();

    expect($breakdown->pluck('bank_name')->all())->toBe(['Algebra', 'Geometry'])
        ->and($breakdown[0])->toMatchArray([
            'bank_name' => 'Algebra',
            'correct_count' => 1,
            'total_count' => 2,
            'earned_weight' => 1.0,
            'max_weight' => 3.0,
            'percentage' => 33.3,
        ])
        ->and($breakdown[1])->toMatchArray([
            'bank_name' => 'Geometry',
            'correct_count' => 1,
            // The blank fourth question still counts against the student: it was
            // on the paper, and only the attempt knows that - no answer row for
            // it was ever written.
            'total_count' => 2,
            'earned_weight' => 1.5,
            'max_weight' => 3.0,
            'percentage' => 50.0,
        ]);
});

it('draws the breakdown into the admin results page', function () {
    $user = User::factory()->create();
    $seed = seedTwoBankPaper($user);
    [$algebraEasy, $algebraHard, $geometryOne] = $seed['questions'];

    submitAnswers($user, $seed['exam'], [
        'answers' => [
            $algebraEasy->id => 0,
            $algebraHard->id => 0,
            $geometryOne->id => 0,
        ],
    ]);

    $html = actingAsAdmin()
        ->get(route('admin.exam-results', $seed['exam']->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Sual Bankı Analizi')
        ->and($html)->toContain('Algebra')
        // Algebra: both right, 3 of 3. Percentage and counts are separate
        // elements now, so they are asserted separately.
        ->and($html)->toContain('>100%</span>')
        ->and($html)->toContain('2/2 sual')
        ->and($html)->toContain('3/3 bal')
        // Geometry: one of two, 1.5 of 3.
        ->and($html)->toContain('>50%</span>')
        ->and($html)->toContain('1/2 sual')
        ->and($html)->toContain('1.5/3 bal')
        // One state per row drives both the number and the bar - see the
        // .progress-bar cascade note in app.css for what the old two-class
        // version rendered.
        ->and($html)->toContain('ps-bank-pct--good')
        ->and($html)->toContain('ps-bank-bar--good')
        ->and($html)->toContain('ps-bank-pct--mid')
        ->and($html)->toContain('ps-bank-bar--mid')
        // The headline score is the weighted one, out of the paper's weight.
        ->and($html)->toContain('4.5 / 6');
});
