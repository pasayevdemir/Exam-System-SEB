/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * Ask before submitting a form that says it wants asking.
 *
 * These were onsubmit="return confirm('...')" attributes. An inline handler is
 * script as far as a Content-Security-Policy is concerned, so the five of them
 * were the last thing standing between the app and script-src 'self'.
 *
 * The message stays on the form in data-confirm, next to the action it guards,
 * which is where it was.
 */
export function initConfirmSubmit(doc = document) {
    doc.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (! window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
}
