/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * The results search box.
 *
 * Enter submits it, which a lone text input inside a larger form does not do on
 * its own, and an empty box takes focus - an admin opening this page has come to
 * look someone up.
 */
export function start(doc = document) {
    const form = doc.getElementById('searchForm');
    const input = doc.getElementById('search');

    if (!form || !input) return;

    input.addEventListener('keypress', event => {
        if (event.key !== 'Enter') return;

        event.preventDefault();
        form.submit();
    });

    if (!input.value) {
        input.focus();
    }
}
