/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * The channel between "a response carried the server's remaining_seconds" and
 * "the countdown re-anchors itself".
 *
 * This used to be a bare `onServerClock` variable in the same closure: the
 * timer assigned it, autosave and keep-alive called it, and nothing said so.
 * Same wiring, now explicit — and a module that only reports has no way to
 * reach into the timer's internals.
 */
export function createServerClock() {
    let subscriber = null;

    return {
        onSync(fn) {
            subscriber = fn;
        },

        report(seconds) {
            if (subscriber) {
                subscriber(seconds);
            }
        },
    };
}
