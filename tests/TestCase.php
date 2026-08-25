<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Render views without the Vite tags.
     *
     * public/build is a build artifact and gitignored, so on any fresh checkout
     * — CI's, or a colleague's before their first `npm run build` — the manifest
     * does not exist and every view extending the layout throws before a single
     * assertion runs. Passing locally only meant a build happened to be lying
     * around.
     *
     * Nothing under test is about which hashed filename the manifest holds; the
     * frontend is built and checked in the workflow's other job.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
