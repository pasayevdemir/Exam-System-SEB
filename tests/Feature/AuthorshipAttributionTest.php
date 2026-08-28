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
 * The attribution is part of the work, and this is what keeps it that way.
 *
 * Copyright notices rot quietly. A file gets rewritten, a template gets
 * replaced, someone runs a formatter that eats the header — and nothing fails,
 * so nobody notices until the notice is simply gone and there is no record of
 * when it went. Every other guarantee in this repository is enforced by a test;
 * this one is too.
 *
 * So these tests fail loudly when authorship is removed from any of the places
 * it is meant to live: the source headers, the licence files, the package
 * manifests, the rendered HTML, the HTTP headers, and the seal in
 * App\Support\Authorship. That has two effects. Ordinarily it catches honest
 * drift. When the removal is not honest, it means the suite had to be edited
 * too — a separate, deliberate, individually dated act, recorded in version
 * control next to the removal it was covering.
 *
 * None of it prevents copying. It makes copying leave a mark.
 *
 * @see Authorship
 * @see NOTICE
 */

use App\Support\Authorship;

/**
 * @return array<int, string> every first-party source file in the work
 */
function attributedSourceFiles(): array
{
    $roots = [
        base_path('app'),
        base_path('config'),
        base_path('database'),
        base_path('routes'),
        base_path('tests'),
        base_path('resources/js'),
        base_path('resources/css'),
        base_path('resources/views'),
    ];

    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $found */
        $found = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
            '/\.(php|js|css)$/'
        );

        foreach ($found as $file) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

describe('the authorship seal', function () {
    // The seal is what makes tampering detectable rather than merely visible.
    // Editing the name and leaving the digest, or editing the digest and
    // leaving the name, both land here.
    it('still certifies the identity it was computed over', function () {
        expect(Authorship::verify())->toBeTrue();
    });

    it('names the author', function () {
        expect(Authorship::AUTHOR)->toBe('Damir Pashayev')
            ->and(Authorship::EMAIL)->toBe('pashayevdamir@gmail.com')
            ->and(Authorship::LINK)->toBe('https://github.com/pasayevdemir');
    });

    // Independently recomputed here rather than read back from the constant, so
    // that rewriting the constant alone cannot make this pass.
    it('matches a SHA-256 recomputed from the canonical string', function () {
        expect(hash('sha256', Authorship::CANONICAL))->toBe(Authorship::FINGERPRINT);
    });

    // The base64 copy exists precisely because it survives a find-and-replace
    // for the author's name. If someone ran one, this is where it surfaces.
    it('holds a second copy of the identity that contains none of its letters', function () {
        expect(base64_decode(Authorship::SEAL, true))->toBe(Authorship::CANONICAL)
            ->and(Authorship::SEAL)->not->toContain('Pashayev');
    });
});

describe('the source headers', function () {
    // The broad one: not a single first-party source file may lose its header.
    it('appear in every first-party source file', function () {
        $unattributed = array_values(array_filter(
            attributedSourceFiles(),
            fn (string $path) => ! str_contains((string) file_get_contents($path), 'Pashayev')
        ));

        expect($unattributed)->toBe([]);
    });

    it('cover a meaningful number of files', function () {
        // Guards against the test above passing vacuously if the globs ever
        // stop matching anything.
        expect(count(attributedSourceFiles()))->toBeGreaterThan(150);
    });
});

describe('the licence files', function () {
    it('are present and name the author', function () {
        foreach (['LICENSE', 'NOTICE', 'AUTHORS'] as $file) {
            $path = base_path($file);

            expect(is_file($path))->toBeTrue("{$file} is missing");
            expect((string) file_get_contents($path))->toContain('Damir Pashayev');
        }
    });

    it('state that the work is not licensed away', function () {
        expect((string) file_get_contents(base_path('LICENSE')))
            ->toContain('All rights reserved');
    });
});

describe('the package manifests', function () {
    it('declare the author', function () {
        foreach (['composer.json', 'package.json'] as $file) {
            expect((string) file_get_contents(base_path($file)))
                ->toContain('pashayevdamir@gmail.com');
        }
    });

    it('declare the work as proprietary', function () {
        foreach (['composer.json', 'package.json'] as $file) {
            $manifest = json_decode((string) file_get_contents(base_path($file)), true);

            expect($manifest['license'] ?? null)->toBe('proprietary');
        }
    });
});

describe('the authorship config', function () {
    // config/authorship.php feeds the footer and the provenance comment in the
    // layout. It derives from the class rather than restating the name, and
    // this is what holds it to that: editing the config alone cannot quietly
    // put a different name in the footer of every page.
    it('derives every value from the seal rather than restating it', function () {
        expect(config('authorship.author'))->toBe(Authorship::AUTHOR)
            ->and(config('authorship.email'))->toBe(Authorship::EMAIL)
            ->and(config('authorship.github'))->toBe(Authorship::LINK)
            ->and(config('authorship.project'))->toBe(Authorship::PROJECT)
            ->and(config('authorship.fingerprint'))->toBe(Authorship::FINGERPRINT);
    });

    it('reports one fingerprint, not two', function () {
        // The page renders the fingerprint from both the meta tags and the
        // footer comment. They have to agree, or the provenance claim is
        // self-contradicting.
        $html = test()->get(route('student.login'))->getContent();

        expect(substr_count((string) $html, Authorship::FINGERPRINT))->toBeGreaterThanOrEqual(2);
    });
});

describe('the running application', function () {
    // The visible one: a person looking at the page, not its source, still
    // sees who wrote it.
    it('names its author in the footer of every page', function () {
        $html = (string) test()->get(route('student.login'))->getContent();

        expect($html)->toContain('hazırlayan')
            ->and($html)->toContain(Authorship::AUTHOR);
    });

    // Source headers do not survive Blade compilation, so the served page has
    // to carry the attribution itself.
    it('names its author in the HTML of every page it renders', function () {
        $html = test()->get(route('student.login'))->getContent();

        expect($html)->toContain('Damir Pashayev')
            ->and($html)->toContain(Authorship::FINGERPRINT);
    });

    // And so does the response, for anyone reading it with curl rather than a
    // browser.
    it('names its author in its response headers', function () {
        $headers = test()->get(route('student.login'))->headers;

        expect($headers->get('X-Author'))->toContain('Damir Pashayev')
            ->and($headers->get('X-Copyright'))->toContain('All rights reserved')
            ->and($headers->get('X-Authorship-Fingerprint'))->toBe(Authorship::FINGERPRINT);
    });
});

describe('the compiled assets', function () {
    // Production serves the bundle, never resources/js — so if attribution is
    // going to reach a deployed copy at all, it reaches it through here. The
    // banner lives in vite.config.js.
    it('are configured to carry the notice into every bundle', function () {
        $config = (string) file_get_contents(base_path('vite.config.js'));

        expect($config)->toContain('Damir Pashayev')
            ->and($config)->toContain(Authorship::FINGERPRINT)
            ->and($config)->toContain('legalComments');
    });

    // Only meaningful once someone has actually run `npm run build`, so it is
    // skipped rather than failed on a source-only checkout.
    it('carry the notice in the built output', function () {
        $manifest = base_path('public/build/manifest.json');

        if (! is_file($manifest)) {
            test()->markTestSkipped('no build present; run `npm run build`');
        }

        $bundles = glob(base_path('public/build/assets/*.{js,css}'), GLOB_BRACE) ?: [];
        $unattributed = array_values(array_filter(
            $bundles,
            fn (string $path) => ! str_contains((string) file_get_contents($path), 'Pashayev')
        ));

        expect($bundles)->not->toBeEmpty()
            ->and($unattributed)->toBe([]);
    });
});
