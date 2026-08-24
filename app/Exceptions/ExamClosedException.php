<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Exceptions;

use App\Models\Exam;
use RuntimeException;

/**
 * Thrown when generation finds the exam deactivated under its own row lock.
 *
 * Only ever a lost race: the controller checks is_active before calling, so
 * reaching this means an admin's deactivation committed in between. A distinct
 * type for the same reason as ConcurrentExamAttemptException - "an admin just
 * closed this" is ordinary and needs no log line, unlike the misconfiguration
 * RuntimeException the controller otherwise treats it as.
 */
class ExamClosedException extends RuntimeException
{
    public function __construct(public readonly Exam $exam)
    {
        parent::__construct("Exam {$exam->id} was deactivated before generation could start.");
    }
}
