<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Exam;
use App\Models\ExamQuestionBank;
use App\Models\Question;
use App\Models\QuestionBank;

/** An exam with one bank attached, which is what renders an edit-quota panel. */
function seedAttachedBank(): array
{
    $exam = Exam::factory()->create();
    $bank = QuestionBank::factory()->create();

    $assignment = ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => 1,
        'quota_medium' => 2,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    return compact('exam', 'bank', 'assignment');
}

function visitBanksPage(Exam $exam)
{
    return test()
        ->withSession(['admin_logged_in' => true])
        ->get(route('admin.exam-banks', $exam->id));
}

it('renders the exam banks page', function () {
    $seed = seedAttachedBank();

    visitBanksPage($seed['exam'])
        ->assertOk()
        ->assertSee($seed['bank']->name);
});

it('never puts the collapse class on a table row', function () {
    $seed = seedAttachedBank();

    $html = visitBanksPage($seed['exam'])->assertOk()->getContent();

    // Bootstrap animates a collapse via height + overflow:hidden, which a
    // table-row cannot honour - the panel opened and snapped straight shut.
    // The collapsing element has to be a block inside the cell.
    expect($html)->not->toMatch('/<tr[^>]*class="[^"]*\bcollapse\b/');
});

it('exposes the edit panel as a collapsible block with its quota fields', function () {
    $seed = seedAttachedBank();

    $html = visitBanksPage($seed['exam'])->assertOk()->getContent();
    $target = 'editQuota' . $seed['assignment']->id;

    expect($html)->toContain('data-bs-target="#' . $target . '"')
        ->and($html)->toMatch('/<div class="collapse" id="' . $target . '"/')
        ->and($html)->toContain('name="quota_easy"')
        ->and($html)->toContain('name="quota_medium"')
        ->and($html)->toContain('name="quota_hard"');
});

/*
|--------------------------------------------------------------------------
| Pool strength indicator (Faza 2.2)
|--------------------------------------------------------------------------
*/

function seedBankWithEasyPool(int $quota, int $poolSize): array
{
    $exam = Exam::factory()->create();
    $bank = QuestionBank::factory()->create();

    Question::factory()->count($poolSize)->create([
        'question_bank_id' => $bank->id,
        'difficulty' => 'easy',
    ]);

    ExamQuestionBank::create([
        'exam_id' => $exam->id,
        'question_bank_id' => $bank->id,
        'quota_easy' => $quota,
        'quota_medium' => 0,
        'quota_hard' => 0,
        'sort_order' => 0,
    ]);

    return compact('exam', 'bank');
}

it('flags a pool under 2x quota as weak', function () {
    $seed = seedBankWithEasyPool(quota: 5, poolSize: 6); // 1.2x

    $html = visitBanksPage($seed['exam'])->assertOk()->getContent();

    expect($html)->toContain('bg-danger ms-1" title="6 available / 5 needed">ZƏİF');
});

it('flags a pool between 2x and 4x quota as medium', function () {
    $seed = seedBankWithEasyPool(quota: 5, poolSize: 15); // 3x

    $html = visitBanksPage($seed['exam'])->assertOk()->getContent();

    expect($html)->toContain('bg-warning text-dark ms-1" title="15 available / 5 needed">ORTA');
});

it('flags a pool at or above 4x quota as good', function () {
    $seed = seedBankWithEasyPool(quota: 5, poolSize: 20); // 4x

    $html = visitBanksPage($seed['exam'])->assertOk()->getContent();

    expect($html)->toContain('bg-success ms-1" title="20 available / 5 needed">YAXŞI');
});

it('shows no strength badge for a difficulty with zero quota', function () {
    // quota_medium and quota_hard are both 0; easy has a large surplus (would
    // render "YAXŞI") so this isolates that the zero-quota columns specifically
    // render nothing, rather than asserting the whole page has no tier text.
    $seed = seedBankWithEasyPool(quota: 5, poolSize: 20);

    $html = visitBanksPage($seed['exam'])->assertOk()->getContent();

    // $poolTier() returns null for quota === 0, so a badge for medium/hard would
    // always show a title ending in "/ 0 needed" - that must never appear.
    expect($html)->not->toContain('/ 0 needed');
});
