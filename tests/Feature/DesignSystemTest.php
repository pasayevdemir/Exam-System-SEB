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
 * The two stylesheet rules that a rendering test cannot see.
 *
 * Every other test here asserts on markup, which is why the bank breakdown could
 * ship with a passing suite while every one of its bars rendered green: the
 * class was in the HTML exactly as asserted, and `.progress-bar` then overrode
 * it in the cascade. Bootstrap's bg-* utilities are !important at the same
 * specificity as a bare element class, so whichever rule the bundler emits last
 * wins - and app.css imports Bootstrap at the top, so app.css always wins.
 *
 * These read the source stylesheet instead. Neither is a style opinion; both pin
 * a specific defect that markup assertions are blind to.
 */
function appStylesheet(): string
{
    return (string) file_get_contents(resource_path('css/app.css'));
}

/**
 * The body of the rule whose selector list contains exactly $selector.
 *
 * Written out rather than a single regex because these selectors are declared
 * as groups - ".badge.bg-success, .status-badge.bg-success { … }" - and a
 * pattern anchored on "selector {" silently matches none of them.
 */
function cssRule(string $selector): ?string
{
    // Comments first: a brace inside one would derail the split below.
    $css = (string) preg_replace('#/\*.*?\*/#s', '', appStylesheet());

    foreach (explode('}', $css) as $chunk) {
        if (! str_contains($chunk, '{')) {
            continue;
        }

        [$selectors, $body] = explode('{', $chunk, 2);

        if (in_array($selector, array_map('trim', explode(',', $selectors)), true)) {
            return $body;
        }
    }

    return null;
}

describe('the progress bar cascade', function () {
    // The regression this file exists for.
    it('does not force one colour on every bar in the app', function () {
        $rule = cssRule('.progress-bar');

        expect($rule)->not->toBeNull()
            ->and($rule)->not->toMatch('/background(-color)?\s*:[^;]*!important/');
    });

    // Removing the !important is only half of it: the modifiers have to outrank
    // the bare .progress-bar default on specificity, or the colour goes back to
    // depending on emit order.
    it('colours each breakdown state from a modifier that outranks the default', function () {
        foreach (['good', 'mid', 'weak'] as $state) {
            expect(appStylesheet())->toContain('.progress-bar.ps-bank-bar--'.$state);
        }
    });
});

describe('the badge palette', function () {
    // Bootstrap ships bg-warning as a solid #ffc107. With success and danger
    // both restyled as pale pills and warning left alone, the three states of
    // one component rendered in two different visual families.
    it('restyles all three badge states, not two of them', function () {
        foreach (['success', 'warning', 'danger'] as $state) {
            expect(cssRule('.badge.bg-'.$state))->not->toBeNull();
        }
    });
});
