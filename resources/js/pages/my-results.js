import { createLivePoll } from '../live-poll.js';

/**
 * A pending file submission turns into a score the moment an admin grades it;
 * without this the student sits on "Grading Pending" until they reload.
 */
export function start(doc = document) {
    const listEl = doc.getElementById('resultListLive');
    if (!listEl) return;

    createLivePoll({
        url: listEl.dataset.url,
        version: listEl.dataset.v,
        target: listEl,
    });
}
