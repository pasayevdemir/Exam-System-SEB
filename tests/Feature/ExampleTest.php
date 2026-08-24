<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */
test('the root url sends visitors to the student sign-in page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/start-exam');
});
