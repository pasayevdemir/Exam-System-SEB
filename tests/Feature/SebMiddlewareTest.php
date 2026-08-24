<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

use App\Models\User;

function actingStudent()
{
    return test()->actingAs(User::factory()->create());
}

it('lets requests through when seb.required is false, regardless of headers', function () {
    config(['seb.required' => false, 'seb.config_key' => 'some-key']);

    actingStudent()->getJson(route('student.keep-alive'))->assertOk();
});

it('fails closed with 503 when required but no config key is set', function () {
    config(['seb.required' => true, 'seb.config_key' => null]);

    actingStudent()->getJson(route('student.keep-alive'))->assertStatus(503);
});

it('rejects a request with no SEB header when required and a key is set', function () {
    config(['seb.required' => true, 'seb.config_key' => 'test-config-key']);

    actingStudent()->getJson(route('student.keep-alive'))->assertStatus(403);
});

it('rejects a request with a wrong hash', function () {
    config(['seb.required' => true, 'seb.config_key' => 'test-config-key']);

    actingStudent()
        ->withHeaders(['X-SafeExamBrowser-ConfigKeyHash' => 'not-the-right-hash'])
        ->getJson(route('student.keep-alive'))
        ->assertStatus(403);
});

it('accepts a request with the correct hash', function () {
    config(['seb.required' => true, 'seb.config_key' => 'test-config-key']);

    $url = route('student.keep-alive');
    $hash = hash('sha256', $url.'test-config-key');

    actingStudent()
        ->withHeaders(['X-SafeExamBrowser-ConfigKeyHash' => $hash])
        ->getJson($url)
        ->assertOk();
});

it('accepts a hash in a different case, since SEB clients are inconsistent about it', function () {
    config(['seb.required' => true, 'seb.config_key' => 'test-config-key']);

    $url = route('student.keep-alive');
    $hash = strtoupper(hash('sha256', $url.'test-config-key'));

    actingStudent()
        ->withHeaders(['X-SafeExamBrowser-ConfigKeyHash' => $hash])
        ->getJson($url)
        ->assertOk();
});

it('does not gate result-viewing routes behind SEB', function () {
    config(['seb.required' => true, 'seb.config_key' => 'test-config-key']);

    // No SEB header at all - my-results must still be reachable from a normal
    // browser, since checking an old grade isn't an integrity concern.
    actingStudent()->get(route('student.my-results'))->assertOk();
});
