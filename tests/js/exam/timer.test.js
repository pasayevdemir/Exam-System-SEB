/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createServerClock } from '../../../resources/js/exam/clock.js';
import { createTimer, formatTime } from '../../../resources/js/exam/timer.js';
import { mountExamDom } from './fixture.js';

let clock;
let submitForm;
let finalizeAnswers;

beforeEach(() => {
    mountExamDom({ timed: true });
    vi.useFakeTimers();
    clock = createServerClock();
    submitForm = vi.fn();
    finalizeAnswers = vi.fn(() => Promise.resolve());
});

afterEach(() => {
    vi.useRealTimers();
});

function makeTimer(remainingSeconds, gracePeriodSeconds = 30) {
    return createTimer({
        remainingSeconds,
        gracePeriodSeconds,
        clock,
        finalizeAnswers,
        submitForm,
    });
}

describe('formatTime', () => {
    it('drops the hour segment under an hour', () => {
        expect(formatTime(90)).toBe('01:30');
        expect(formatTime(3661)).toBe('01:01:01');
    });
});

describe('the countdown', () => {
    it('re-derives from a fixed deadline, so a skipped interval costs nothing', async () => {
        const timer = makeTimer(600);
        timer.start();

        // A background tab where the interval never fired: jump the wall clock
        // without letting the 1s tick run in between.
        vi.setSystemTime(Date.now() + 120000);
        timer.tick();

        expect(document.getElementById('exam-timer').textContent).toBe('08:00');
    });

    it('turns the bar red inside the last minute', () => {
        const timer = makeTimer(45);
        timer.start();

        expect(document.getElementById('timer-bar').className).toContain('ps-timer-danger');
    });
});

// The server is the authority on when the attempt ends; the local clock is a
// convenience that gets corrected by ordinary traffic.
describe('server clock sync', () => {
    it('ignores drift within two seconds so the display does not jitter', () => {
        const timer = makeTimer(600);
        timer.start();

        clock.report(602);

        expect(timer.remaining()).toBe(600);
    });

    it('re-anchors the deadline on a real gap', () => {
        const timer = makeTimer(600);
        timer.start();

        clock.report(120);

        expect(timer.remaining()).toBe(120);
        expect(document.getElementById('exam-timer').textContent).toBe('02:00');
    });

    it('stops listening once auto-submit has started', async () => {
        const timer = makeTimer(0);
        timer.start();

        clock.report(600);

        expect(timer.remaining()).toBe(0);
    });
});

describe('auto-submit', () => {
    it('waits for the grace countdown even when saves finish instantly', async () => {
        const timer = makeTimer(0, 30);
        const done = timer.autoSubmitExam();

        await vi.advanceTimersByTimeAsync(30000);
        expect(submitForm).not.toHaveBeenCalled();

        // grace period (30) + transit buffer (3)
        await vi.advanceTimersByTimeAsync(3000);
        await done;
        expect(submitForm).toHaveBeenCalledTimes(1);
    });

    it('waits for the saves even when they outlast the countdown', async () => {
        finalizeAnswers = vi.fn(() => new Promise(resolve => setTimeout(resolve, 40000)));
        const timer = makeTimer(0, 30);
        const done = timer.autoSubmitExam();

        await vi.advanceTimersByTimeAsync(33000);
        expect(submitForm).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(7000);
        await done;
        expect(submitForm).toHaveBeenCalledTimes(1);
    });

    it('flags the submit as automatic so the server scores from saved drafts', async () => {
        const timer = makeTimer(0, 30);
        const done = timer.autoSubmitExam();

        expect(document.getElementById('auto_submit').value).toBe('1');

        await vi.advanceTimersByTimeAsync(33000);
        await done;
    });

    it('submits once even if the deadline is reached repeatedly', async () => {
        const timer = makeTimer(0, 30);

        const first = timer.autoSubmitExam();
        const second = timer.autoSubmitExam();
        timer.tick();

        await vi.advanceTimersByTimeAsync(33000);
        await Promise.all([first, second]);

        expect(submitForm).toHaveBeenCalledTimes(1);
        expect(finalizeAnswers).toHaveBeenCalledTimes(1);
    });

    it('counts the grace period down on screen', async () => {
        const timer = makeTimer(0, 30);
        const done = timer.autoSubmitExam();

        expect(document.getElementById('autoSubmitCountdown').textContent).toBe('33');

        await vi.advanceTimersByTimeAsync(3000);
        expect(document.getElementById('autoSubmitCountdown').textContent).toBe('30');

        await vi.advanceTimersByTimeAsync(30000);
        await done;
    });
});
