import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createCsrf } from '../../../resources/js/exam/csrf.js';
import { createKeepAlive } from '../../../resources/js/exam/keep-alive.js';
import { createTelemetry } from '../../../resources/js/exam/telemetry.js';
import { createProgress } from '../../../resources/js/exam/progress.js';
import { mountExamDom, respondWith, stubClock, stubCsrf } from './fixture.js';

let dom;

beforeEach(() => {
    dom = mountExamDom({ questions: 3, options: 2 });
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('keep-alive', () => {
    // A session rebuilt while the laptop slept hands back a fresh token. Miss it
    // and the final submit dies on a CSRF mismatch, losing the whole attempt.
    it('adopts a rotated CSRF token into the meta tag and every hidden input', async () => {
        vi.stubGlobal('fetch', respondWith(200, { token: 'rotated-token', remaining_seconds: 300 }));
        const csrf = createCsrf();

        await createKeepAlive({ url: '/student/keep-alive', csrf, clock: stubClock() }).ping();

        expect(csrf.token()).toBe('rotated-token');
        expect(document.querySelector('input[name="_token"]').value).toBe('rotated-token');
    });

    it('leaves the token alone when the response carries none', async () => {
        vi.stubGlobal('fetch', respondWith(200, { remaining_seconds: 300 }));
        const csrf = createCsrf();

        await createKeepAlive({ url: '/student/keep-alive', csrf, clock: stubClock() }).ping();

        expect(csrf.token()).toBe('original-token');
    });

    it('throttles bursts from refocus and reconnect firing together', async () => {
        const fetchMock = respondWith(200, { remaining_seconds: 300 });
        vi.stubGlobal('fetch', fetchMock);
        const keepAlive = createKeepAlive({ url: '/k', csrf: stubCsrf(), clock: stubClock() });

        await keepAlive.ping();
        await keepAlive.ping();
        await keepAlive.ping();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(10000);
        await keepAlive.ping();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('warns when an exam was closed under a sitting student, without blocking them', async () => {
        vi.stubGlobal('fetch', respondWith(200, { exam_active: false, remaining_seconds: 300 }));

        await createKeepAlive({ url: '/k', csrf: stubCsrf(), clock: stubClock() }).ping();

        expect(document.getElementById('examClosedBanner')).not.toBeNull();
    });

    it('reports the server reading to the clock', async () => {
        const clock = stubClock();
        vi.stubGlobal('fetch', respondWith(200, { remaining_seconds: 77 }));

        await createKeepAlive({ url: '/k', csrf: stubCsrf(), clock }).ping();

        expect(clock.report).toHaveBeenCalledWith(77);
    });
});

describe('telemetry', () => {
    // Alt-tabbing rapidly must not turn into a request per flicker.
    it('sends one event per type per five seconds', async () => {
        const fetchMock = respondWith(204);
        vi.stubGlobal('fetch', fetchMock);
        const telemetry = createTelemetry({ url: '/e', csrf: stubCsrf() });

        telemetry.logExamEvent('focus_lost');
        telemetry.logExamEvent('focus_lost');
        telemetry.logExamEvent('focus_lost');

        expect(fetchMock).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(5000);
        telemetry.logExamEvent('focus_lost');
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('throttles each type independently', () => {
        const fetchMock = respondWith(204);
        vi.stubGlobal('fetch', fetchMock);
        const telemetry = createTelemetry({ url: '/e', csrf: stubCsrf() });

        telemetry.logExamEvent('focus_lost');
        telemetry.logExamEvent('copy');
        telemetry.logExamEvent('paste');

        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('blocks copy and paste rather than only recording them', () => {
        vi.stubGlobal('fetch', respondWith(204));
        createTelemetry({ url: '/e', csrf: stubCsrf() }).bind();

        const event = new Event('copy', { cancelable: true });
        document.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });
});

describe('progress', () => {
    // One set, so the counter, the progress bar and the question map can never
    // disagree about what counts as answered.
    it('counts checked options and uploaded files together', () => {
        const progress = createProgress({ totalQuestions: 3 });

        dom.check(1, 0);
        dom.check(2, 1);
        progress.update();

        expect(document.getElementById('answered-count').textContent).toBe('2');
        expect(document.getElementById('modal-unanswered-count').textContent).toBe('1');
    });

    it('marks the matching map button as answered', () => {
        const progress = createProgress({ totalQuestions: 3 });

        dom.check(2, 0);
        progress.update();

        const buttons = document.querySelectorAll('.ps-qmap-btn');
        expect(buttons[0].classList.contains('is-answered')).toBe(false);
        expect(buttons[1].classList.contains('is-answered')).toBe(true);
    });

    // Submitting a partly blank paper is allowed - the count is surfaced so it
    // is never an accident, but nothing is disabled.
    it('says how many are unanswered without blocking submission', () => {
        const progress = createProgress({ totalQuestions: 3 });

        dom.check(1, 0);
        progress.update();

        expect(document.getElementById('submit-warning').textContent)
            .toContain('2 sual cavablanmayıb');
        expect(document.getElementById('modal-unanswered-warning').classList.contains('d-none'))
            .toBe(false);
    });

    it('switches to the all-answered message when nothing is left', () => {
        const progress = createProgress({ totalQuestions: 3 });

        dom.check(1, 0);
        dom.check(2, 0);
        dom.check(3, 0);
        progress.update();

        expect(document.getElementById('submit-warning').textContent)
            .toBe('Bütün suallara cavab verildi. İndi imtahanı təqdim edə bilərsiniz.');
        expect(document.getElementById('progress-bar').style.width).toBe('100%');
    });
});
