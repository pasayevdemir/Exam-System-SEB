<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Exceptions;

use App\Models\ExamAttempt;
use RuntimeException;

/**
 * Thrown when a student tries to start an exam while already sitting another.
 *
 * It is a distinct type rather than a plain RuntimeException so the controller
 * can tell "you already have an exam open" - which the student can act on - from
 * "this exam is misconfigured", which they cannot.
 */
class ConcurrentExamAttemptException extends RuntimeException
{
    public function __construct(public readonly ExamAttempt $openAttempt)
    {
        parent::__construct(
            "User {$openAttempt->user_id} already has exam {$openAttempt->exam_id} in progress."
        );
    }
}
