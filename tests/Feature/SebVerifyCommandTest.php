<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use Illuminate\Support\Facades\Http;

it('passes when everything is healthy', function () {
    // The local dev .env correctly uses http://localhost for APP_URL - that's
    // right for dev, but this test is specifically about the "everything is
    // production-healthy" case, so it needs its own https URL here.
    config(['seb.required' => false, 'app.url' => 'https://exam.example.com']);
    Http::fake(['*/up' => Http::response('OK', 200)]);

    test()->artisan('seb:verify')->assertExitCode(0);
});

it('fails when required but no config key is set', function () {
    config(['seb.required' => true, 'seb.config_key' => null, 'app.url' => 'https://exam.example.com']);
    Http::fake(['*/up' => Http::response('OK', 200)]);

    test()->artisan('seb:verify')->assertExitCode(1);
});

it('fails when the public URL is unreachable', function () {
    config(['seb.required' => false, 'app.url' => 'https://exam.example.com']);
    Http::fake(['*/up' => Http::response('', 502)]);

    test()->artisan('seb:verify')->assertExitCode(1);
});

it('fails when APP_URL is not https', function () {
    config(['seb.required' => false, 'app.url' => 'http://exam.example.com']);
    Http::fake(['*/up' => Http::response('OK', 200)]);

    test()->artisan('seb:verify')->assertExitCode(1);
});
