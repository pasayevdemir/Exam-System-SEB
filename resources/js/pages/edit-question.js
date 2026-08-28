/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import { initQuestionTypeToggle } from '../shared/question-type.js';

/**
 * Editing a question is the create form with values in it, so the only thing
 * this page needs is the same type switch.
 */
export function start(doc = document) {
    initQuestionTypeToggle(doc);
}
