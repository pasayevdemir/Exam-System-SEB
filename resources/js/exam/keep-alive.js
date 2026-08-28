/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * Keeps the session alive while the student is reading rather than answering.
 *
 * Without this, an idle stretch longer than SESSION_LIFETIME expires the
 * session and the final submit dies on a CSRF mismatch, losing the attempt.
 */
const KEEP_ALIVE_MIN_GAP_MS = 10000;
const KEEP_ALIVE_INTERVAL_MS = 300000;

export function createKeepAlive({ url, csrf, clock, doc = document, win = window }) {
    let lastKeepAliveAt = 0;

    function showExamClosedBanner() {
        if (doc.getElementById('examClosedBanner')) return;

        const banner = doc.createElement('div');
        banner.id = 'examClosedBanner';
        banner.className = 'alert alert-warning';
        banner.setAttribute('role', 'alert');
        banner.innerHTML =
            '<i class="fas fa-triangle-exclamation me-2"></i>'
            + '<strong>Bu imtahan administrator tərəfindən dayandırıldı.</strong> '
            + 'Cavablarınız saxlanılır — işinizi bitirib təqdim edə bilərsiniz.';

        doc.querySelector('.container, main, body').prepend(banner);
    }

    function ping() {
        // Refocus and reconnect can both fire in a burst (and repeatedly while
        // a student alt-tabs), so the event-driven calls are throttled - the
        // 5-minute interval below is what guarantees the session stays warm.
        const now = Date.now();

        if (now - lastKeepAliveAt < KEEP_ALIVE_MIN_GAP_MS) {
            return Promise.resolve();
        }

        lastKeepAliveAt = now;

        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(response => (response.ok ? response.json() : null))
            .then(data => {
                if (!data) return;

                clock.report(data.remaining_seconds);

                // Should never fire: an admin cannot deactivate an exam anyone is
                // sitting. If it does, the guard was bypassed - say so, but do not
                // block answering or submitting and throw away half an exam.
                if (data.exam_active === false) {
                    showExamClosedBanner();
                }

                // The session may have been rebuilt (e.g. laptop slept through
                // the interval); adopt whatever token is now current.
                csrf.adopt(data.token);
            })
            .catch(() => {});
    }

    function bind() {
        setInterval(ping, KEEP_ALIVE_INTERVAL_MS);

        // The two moments the local countdown is most likely to have fallen
        // behind the server: the tab was in the background (interval throttled
        // or the machine suspended), or the connection dropped and came back.
        win.addEventListener('online', ping);
        doc.addEventListener('visibilitychange', function () {
            if (!doc.hidden) {
                ping();
            }
        });
    }

    return { ping, bind };
}
