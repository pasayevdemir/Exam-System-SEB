import { initQuestionTypeToggle } from '../shared/question-type.js';

/**
 * Editing a question is the create form with values in it, so the only thing
 * this page needs is the same type switch.
 */
export function start(doc = document) {
    initQuestionTypeToggle(doc);
}
