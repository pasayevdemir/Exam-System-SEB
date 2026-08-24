<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Support\MarkdownRenderer;

beforeEach(function () {
    // Markdown is a rendering concern; SEB enforcement has its own suite.
    config(['seb.required' => false]);
});

/**
 * An active exam holding one question with the given text and one option, so a
 * test can assert on exactly what the student's page does with that text.
 *
 * @return array{exam: Exam, question: Question}
 */
function seedMarkdownAttempt(User $user, string $questionText, string $answerText = 'Option A'): array
{
    $exam = Exam::factory()->create(['is_active' => true]);
    $bank = QuestionBank::factory()->create();

    $attempt = ExamAttempt::create([
        'exam_id' => $exam->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    $question = Question::factory()->create([
        'question_bank_id' => $bank->id,
        'question_type' => 'single',
        'question_text' => $questionText,
    ]);

    $answer = Answer::create([
        'question_id' => $question->id,
        'answer_text' => $answerText,
        'is_correct' => true,
    ]);

    ExamAttemptQuestion::create([
        'exam_attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'display_order' => 0,
        'weight_at_generation' => 1.0,
        'answer_display_order' => [$answer->id],
    ]);

    return ['exam' => $exam, 'question' => $question];
}

function examPageFor(User $user, string $questionText, string $answerText = 'Option A')
{
    $seed = seedMarkdownAttempt($user, $questionText, $answerText);

    return test()->actingAs($user)->get(route('student.exam', $seed['exam']->id))->assertOk();
}

it('renders markdown emphasis in the question text', function () {
    examPageFor(User::factory()->create(), 'What is **binary** search?')
        ->assertSee('<strong>binary</strong>', false);
});

it('renders a markdown table in the question text', function () {
    $text = "Compare:\n\n| a | b |\n|---|---|\n| 1 | 2 |";

    examPageFor(User::factory()->create(), $text)
        ->assertSee('<table>', false)
        ->assertSee('<th>a</th>', false);
});

// The rendered HTML is echoed unescaped, so an admin pasting a script tag would
// otherwise be executing it in every student's browser mid-exam.
it('escapes raw HTML in the question text instead of executing it', function () {
    examPageFor(User::factory()->create(), 'Trick: <script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('drops javascript: links from the question text', function () {
    examPageFor(User::factory()->create(), 'See [here](javascript:alert(1))')
        ->assertDontSee('href="javascript:', false);
});

// Block content inside a <label> is invalid and breaks click-to-select, so the
// answer options go through renderInline().
it('renders an answer option without a wrapping paragraph', function () {
    examPageFor(User::factory()->create(), 'Question', '**Bold** option')
        ->assertSee('<span class="ps-markdown"><strong>Bold</strong> option</span>', false);
});

it('marks up math for the client-side typesetter', function () {
    examPageFor(User::factory()->create(), 'Solve $E=mc^2$')
        ->assertSee('<span class="ps-math" data-display="0">E=mc^2</span>', false);
});

// The whole point of claiming math before markdown: emphasis would otherwise
// eat the subscripts.
it('keeps underscores intact inside a formula', function () {
    expect(MarkdownRenderer::render('$x_1 y_2$'))
        ->toContain('x_1 y_2')
        ->not->toContain('<em>');
});

it('does not treat currency amounts as math', function () {
    expect(MarkdownRenderer::render('Costs $5 and $10'))
        ->not->toContain('ps-math');
});

it('renders a question whose entire text is zero', function () {
    expect(MarkdownRenderer::render('0'))->toContain('0');
});

// The grading list previews a question in one line; truncating the markdown
// source would leave a half-open ``` fence on screen instead of the question.
it('previews a question with code as readable plain text', function () {
    $text = "What does this print?\n\n```php\necho 1 + 1;\n```";

    expect(MarkdownRenderer::plainText($text))
        ->toBe('What does this print? echo 1 + 1;');
});

it('truncates a long preview on a character budget', function () {
    $preview = MarkdownRenderer::plainText(str_repeat('word ', 100), 40);

    expect(mb_strlen($preview))->toBeLessThanOrEqual(41)
        ->and($preview)->toEndWith('…');
});

it('strips markdown syntax out of a preview', function () {
    expect(MarkdownRenderer::plainText('**bold**, *italic* and [a link](https://x.test)'))
        ->toBe('bold, italic and a link');
});

// plainText returns text, not HTML: angle brackets an admin typed stay literal
// so the preview shows what was written. Callers escape on output ({{ }}).
it('returns admin-typed tags as literal preview text', function () {
    expect(MarkdownRenderer::plainText('Trick: <script>alert(1)</script>'))
        ->toBe('Trick: <script>alert(1)</script>');
});
