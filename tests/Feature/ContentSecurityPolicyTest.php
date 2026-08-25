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
 * The policy, and the two things that would quietly defeat it.
 *
 * script-src 'self' is only worth anything while every view keeps its scripts in
 * files. One inline <script> or one onclick="..." and the fix is to add
 * 'unsafe-inline', at which point the header is decoration - so the last two
 * tests here scan the views rather than trusting that nobody will add one.
 */

use App\Models\Exam;
use App\Models\User;

function cspDirectives(string $url): array
{
    $header = test()->get($url)->headers->get('Content-Security-Policy');

    expect($header)->not->toBeNull();

    return collect(explode(';', $header))
        ->mapWithKeys(function (string $directive) {
            [$name, $value] = array_pad(explode(' ', trim($directive), 2), 2, '');

            return [$name => $value];
        })
        ->all();
}

/**
 * @return array<int, string> every blade in the application
 */
function bladeFiles(): array
{
    return array_map(
        fn (SplFileInfo $file) => $file->getPathname(),
        iterator_to_array(new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))),
            '/\.blade\.php$/'
        ), false)
    );
}

describe('the policy header', function () {
    it('is sent with an ordinary page', function () {
        expect(cspDirectives(route('student.login')))->toHaveKey('default-src');
    });

    // The one directive the whole refactor was for.
    it('allows script only from this origin', function () {
        expect(cspDirectives(route('student.login'))['script-src'])->toBe("'self'");
    });

    it('does not allow inline script', function () {
        $directives = cspDirectives(route('student.login'));

        expect($directives['script-src'])->not->toContain('unsafe-inline')
            ->and($directives['script-src'])->not->toContain('unsafe-eval');
    });

    // Views carry style="..." attributes, which style-src governs too. An
    // injected style cannot run code, so this one is a deliberate exception
    // rather than an oversight.
    it('allows inline style, and says why by allowing nothing else inline', function () {
        expect(cspDirectives(route('student.login'))['style-src'])->toContain("'unsafe-inline'");
    });

    // <embed> for the PDF preview on the grading page - object-src 'none' would
    // silently blank it.
    it('leaves the pdf preview able to embed', function () {
        expect(cspDirectives(route('student.login'))['object-src'])->toBe("'self'");
    });

    it('refuses to be framed and pins where forms can post', function () {
        $directives = cspDirectives(route('student.login'));

        expect($directives['frame-ancestors'])->toBe("'none'")
            ->and($directives['form-action'])->toBe("'self'")
            ->and($directives['base-uri'])->toBe("'self'");
    });

    it('covers a page behind a login too', function () {
        Exam::factory()->create(['is_active' => true]);

        test()->actingAs(User::factory()->create())
            ->get(route('student.exams'))
            ->assertHeader('Content-Security-Policy');
    });
});

describe('the views', function () {
    // A JSON block is data: the browser parses it, never executes it, which is
    // why the exam page is allowed to keep one.
    it('carry no script the browser would execute', function () {
        $offenders = [];

        foreach (bladeFiles() as $file) {
            preg_match_all('/<script\b[^>]*>/i', (string) file_get_contents($file), $matches);

            foreach ($matches[0] as $tag) {
                if (! str_contains($tag, 'application/json')) {
                    $offenders[] = basename($file).': '.$tag;
                }
            }
        }

        expect($offenders)->toBe([]);
    });

    it('carry no inline event handlers', function () {
        $offenders = [];

        foreach (bladeFiles() as $file) {
            if (preg_match_all('/\son[a-z]{3,}\s*=/i', (string) file_get_contents($file), $matches)) {
                $offenders[] = basename($file).': '.implode(', ', $matches[0]);
            }
        }

        expect($offenders)->toBe([]);
    });
});
