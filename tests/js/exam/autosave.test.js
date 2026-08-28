/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { createAutosave } from '../../../resources/js/exam/autosave.js';
import { mountExamDom, respondWith, stubClock, stubCsrf } from './fixture.js';

const QUEUE_KEY = 'examAutosaveQueue_7';

function makeAutosave(clock = stubClock()) {
    return createAutosave({
        url: '/student/exam/7/autosave',
        queueKey: QUEUE_KEY,
        csrf: stubCsrf(),
        clock,
    });
}

function queue() {
    return JSON.parse(sessionStorage.getItem(QUEUE_KEY) || '[]');
}

let dom;

beforeEach(() => {
    sessionStorage.clear();
    dom = mountExamDom({ questions: 2, options: 3 });
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('the offline queue', () => {
    it('keeps one entry per question rather than stacking duplicates', async () => {
        vi.stubGlobal('fetch', respondWith(500));
        const autosave = makeAutosave();

        dom.check(1, 0);
        const first = autosave.autosaveAnswer('1');
        await vi.advanceTimersByTimeAsync(5000);
        await first;

        dom.check(1, 2);
        const second = autosave.autosaveAnswer('1');
        await vi.advanceTimersByTimeAsync(5000);
        await second;

        expect(queue()).toHaveLength(1);
        expect(queue()[0].answer_indexes).toEqual(['2']);
    });

    it('drops an entry once the server accepts it', async () => {
        sessionStorage.setItem(QUEUE_KEY, JSON.stringify([
            { question_id: '1', answer_indexes: ['0'] },
        ]));
        vi.stubGlobal('fetch', respondWith(200, { remaining_seconds: 120 }));

        await makeAutosave().flushQueue();

        expect(queue()).toHaveLength(0);
    });
});

describe('retry budgets', () => {
    it('tries three times with 1s then 2s backoff before giving up', async () => {
        const fetchMock = respondWith(500);
        vi.stubGlobal('fetch', fetchMock);
        const autosave = makeAutosave();

        dom.check(1, 0);
        const done = autosave.autosaveAnswer('1');

        await vi.advanceTimersByTimeAsync(0);
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1000);
        expect(fetchMock).toHaveBeenCalledTimes(2);

        await vi.advanceTimersByTimeAsync(2000);
        expect(fetchMock).toHaveBeenCalledTimes(3);

        await done;
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(queue()).toHaveLength(1);
    });

    // The last-ditch save before an expiry auto-submit races a fixed countdown,
    // so it is deliberately less patient than background autosave.
    it('uses two attempts 300ms apart when finalising for auto-submit', async () => {
        const fetchMock = respondWith(500);
        vi.stubGlobal('fetch', fetchMock);
        const autosave = makeAutosave();

        dom.check(1, 0);
        const done = autosave.finalizeAllAnswers();

        await vi.advanceTimersByTimeAsync(0);
        expect(fetchMock).toHaveBeenCalledTimes(2); // one per question, first attempt

        await vi.advanceTimersByTimeAsync(300);
        expect(fetchMock).toHaveBeenCalledTimes(4); // second attempt for both

        await vi.advanceTimersByTimeAsync(5000);
        await done;
        expect(fetchMock).toHaveBeenCalledTimes(4);
    });
});

describe('a 409 from an expired attempt', () => {
    it('is not retried and is not queued', async () => {
        const fetchMock = respondWith(409);
        vi.stubGlobal('fetch', fetchMock);
        const autosave = makeAutosave();

        dom.check(1, 0);
        await autosave.autosaveAnswer('1');
        await vi.advanceTimersByTimeAsync(10000);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(queue()).toHaveLength(0);
        expect(document.getElementById('autosave-status').textContent).toBe('Vaxt limiti bitdi');
    });

    it('clears an already-queued answer instead of retrying it forever', async () => {
        sessionStorage.setItem(QUEUE_KEY, JSON.stringify([
            { question_id: '1', answer_indexes: ['0'] },
        ]));
        vi.stubGlobal('fetch', respondWith(409));

        await makeAutosave().flushQueue();
        await vi.advanceTimersByTimeAsync(10000);

        expect(queue()).toHaveLength(0);
    });
});

// The sequence numbers exist for exactly this: a slow failing request must not
// resurrect an answer the student has since changed and successfully saved.
describe('superseded requests', () => {
    it('does not queue a stale failure after a newer save succeeded', async () => {
        let call = 0;
        vi.stubGlobal('fetch', vi.fn(() => {
            call++;

            // First save (three attempts) fails; everything after it succeeds.
            const failing = call <= 3;

            return Promise.resolve({
                ok: !failing,
                status: failing ? 500 : 200,
                json: () => Promise.resolve({ remaining_seconds: 90 }),
            });
        }));

        const autosave = makeAutosave();

        dom.check(1, 0);
        const stale = autosave.autosaveAnswer('1');

        dom.check(1, 1);
        const fresh = autosave.autosaveAnswer('1');

        await vi.advanceTimersByTimeAsync(5000);
        await Promise.all([stale, fresh]);

        expect(queue()).toHaveLength(0);
        expect(document.getElementById('autosave-status').textContent).toBe('Saxlanıldı');
    });
});

describe('the connection indicator', () => {
    it('reports how many answers are waiting when saves fail', async () => {
        vi.stubGlobal('fetch', respondWith(500));
        const autosave = makeAutosave();

        dom.check(1, 0);
        const done = autosave.autosaveAnswer('1');
        await vi.advanceTimersByTimeAsync(5000);
        await done;

        expect(document.getElementById('connection-label').textContent)
            .toBe('1 cavab növbədə — yenidən cəhd ediləcək');
        expect(document.getElementById('connection-dot').className).toContain('bg-danger');
    });

    it('goes back to connected once the queue drains', async () => {
        vi.stubGlobal('fetch', respondWith(200, { remaining_seconds: 60 }));

        await makeAutosave().flushQueue();

        expect(document.getElementById('connection-label').textContent).toBe('Bağlantı var');
    });
});

describe('the server clock', () => {
    it('reports remaining_seconds from every accepted save', async () => {
        const clock = stubClock();
        vi.stubGlobal('fetch', respondWith(200, { remaining_seconds: 42 }));
        const autosave = makeAutosave(clock);

        dom.check(1, 0);
        await autosave.autosaveAnswer('1');

        expect(clock.report).toHaveBeenCalledWith(42);
    });
});

// A queued answer is by definition older than whatever the student has since
// selected. Re-sending it while a newer save is still in flight lets the stale
// value land last and win on the server - the change the student actually made
// is then neither saved nor queued, and a timed exam is graded from the old
// draft.
describe('a queued answer racing a newer save', () => {
    it('is not re-sent while a save for the same question is in flight', async () => {
        sessionStorage.setItem(QUEUE_KEY, JSON.stringify([
            { question_id: '1', answer_indexes: ['0'] },
        ]));

        const sent = [];
        let releaseInFlight;
        vi.stubGlobal('fetch', vi.fn((url, options) => {
            sent.push(JSON.parse(options.body));

            return new Promise(resolve => {
                releaseInFlight = () => resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ remaining_seconds: 300 }),
                });
            });
        }));

        const autosave = makeAutosave();

        dom.check(1, 2);
        const saving = autosave.autosaveAnswer('1');

        await autosave.flushQueue();

        expect(sent).toHaveLength(1);
        expect(sent[0].answer_indexes).toEqual(['2']);

        releaseInFlight();
        await saving;
        expect(queue()).toHaveLength(0);
    });
});
