/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import { vi } from 'vitest';

/**
 * The parts of the exam page the modules actually reach for. Kept minimal on
 * purpose: a test that needs a new element says so by adding it here, which
 * makes the DOM contract each module depends on visible.
 */
export function mountExamDom({ questions = 1, options = 2, timed = false } = {}) {
    const inputs = [];

    for (let q = 1; q <= questions; q++) {
        for (let o = 0; o < options; o++) {
            inputs.push(
                `<input class="question-input" type="radio" name="answers[${q}]"
                        data-question="${q}" value="${o}">`
            );
        }
        inputs.push(`<div id="question-card-${q}"></div>`);
        inputs.push(`<button class="ps-qmap-btn" data-question="${q}"></button>`);
    }

    document.body.innerHTML = `
        <meta name="csrf-token" content="original-token">
        <form id="examForm">
            <input type="hidden" name="_token" value="original-token">
            <input type="hidden" id="auto_submit" value="0">
            ${inputs.join('\n')}
        </form>
        <span id="answered-count"></span>
        <span id="modal-answered-count"></span>
        <span id="modal-unanswered-count"></span>
        <div id="modal-unanswered-warning"></div>
        <div id="progress-bar"></div>
        <div id="submit-warning"></div>
        <div id="autosave-status"></div>
        <span id="connection-dot"></span>
        <span id="connection-label"></span>
        ${timed ? '<span id="exam-timer"></span><div id="timer-bar"></div><div id="timeUpModal"></div><span id="autoSubmitCountdown"></span>' : ''}
    `;

    // The meta tag has to be in the document for createCsrf to find it; jsdom
    // is happy to keep it in the body.
    return {
        check(questionId, optionValue) {
            const input = document.querySelector(
                `.question-input[data-question="${questionId}"][value="${optionValue}"]`
            );
            input.checked = true;

            return input;
        },
    };
}

export function stubCsrf() {
    return {
        token: () => 'original-token',
        adopt: vi.fn(),
    };
}

export function stubClock() {
    return {
        report: vi.fn(),
        onSync: vi.fn(),
    };
}

/** A fetch stub that answers with the given status, once per call. */
export function respondWith(status, body = {}) {
    return vi.fn(() => Promise.resolve({
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    }));
}
