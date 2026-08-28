/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * Anti-cheat telemetry: this only measures, it never blocks navigation - a
 * browser cannot be stopped from switching tabs or losing focus. Right-click,
 * copy and paste are the exception, since those actually can be blocked.
 *
 * Throttled per event type so rapid alt-tabbing or focus flicker does not spam
 * the server (which independently rate-limits this route too).
 */
const EVENT_THROTTLE_MS = 5000;

export function createTelemetry({ url, csrf, doc = document, win = window }) {
    const lastEventAt = {};

    function logExamEvent(type) {
        const now = Date.now();

        if (lastEventAt[type] && (now - lastEventAt[type]) < EVENT_THROTTLE_MS) {
            return;
        }

        lastEventAt[type] = now;

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf.token(),
            },
            body: JSON.stringify({ type }),
        }).catch(() => {});
    }

    function bind() {
        doc.addEventListener('visibilitychange', function () {
            if (doc.hidden) {
                logExamEvent('tab_hidden');
            }
        });

        win.addEventListener('blur', function () {
            logExamEvent('focus_lost');
        });

        doc.addEventListener('fullscreenchange', function () {
            if (!doc.fullscreenElement) {
                logExamEvent('fullscreen_exit');
            }
        });

        doc.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            logExamEvent('contextmenu');
        });

        doc.addEventListener('copy', function (e) {
            e.preventDefault();
            logExamEvent('copy');
        });

        doc.addEventListener('paste', function (e) {
            e.preventDefault();
            logExamEvent('paste');
        });

        const fullscreenBtn = doc.getElementById('enterFullscreenBtn');

        if (fullscreenBtn && doc.documentElement.requestFullscreen) {
            fullscreenBtn.addEventListener('click', function () {
                doc.documentElement.requestFullscreen().catch(() => {});
            });
        } else if (fullscreenBtn) {
            fullscreenBtn.disabled = true;
        }
    }

    return { logExamEvent, bind };
}
