import './bootstrap';

// Bootstrap's own JS bundle (dropdowns, modals, alert dismiss via
// data-bs-dismiss, etc.) — auto-init relies on `bootstrap` being global,
// same as when it was loaded from the CDN <script> tag.
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import katex from 'katex';
import 'katex/dist/katex.min.css';

/**
 * Typeset the `$...$` spans the markdown renderer left behind.
 *
 * MathInlineParser claims the formula server-side and emits its source escaped
 * inside <span class="ps-math">, so what lands here is exactly what the admin
 * typed — markdown never got to reinterpret the underscores and backslashes.
 *
 * throwOnError stays off on purpose: a malformed formula in one question must
 * not take down the exam page, so KaTeX renders it in red and the student can
 * still answer everything else.
 */
function renderMath(root = document) {
    root.querySelectorAll('.ps-math:not(.ps-math-done)').forEach(el => {
        katex.render(el.textContent, el, {
            displayMode: el.dataset.display === '1',
            throwOnError: false,
        });
        el.classList.add('ps-math-done');
    });
}

window.psRenderMath = renderMath;

document.addEventListener('DOMContentLoaded', () => renderMath());

/**
 * Poll a server-rendered fragment and swap it in when it actually changes.
 *
 * The student pages are otherwise fully server-rendered, so an admin flipping an
 * exam active is invisible until the student reloads. This closes that gap
 * without any broadcasting infrastructure: the endpoint renders the same partial
 * the page did, hashes it, and answers `{changed:false}` when the caller's hash
 * still matches — so the steady state costs ~20 bytes per tick.
 *
 * Options:
 *   url        endpoint answering {changed:false} | {changed:true, v, html}
 *   version    the hash the page was rendered with
 *   target     element whose innerHTML gets replaced
 *   canSwap    optional () => bool; a false defers the swap (see applyPending)
 *
 * Returns { applyPending } so a caller that blocked a swap can release it later.
 */
window.psLivePoll = function psLivePoll({ url, version, target, intervalMs = 10000, canSwap = null }) {
    const MAX_BACKOFF_MS = 60000;

    let currentVersion = version;
    let inFlight = false;
    let failures = 0;
    let timer = null;
    let pending = null;

    // Nothing needs re-binding after a swap: the only handlers on the replaced
    // nodes are Bootstrap's data-bs-toggle triggers, which are delegated.
    function swap(html, v) {
        target.innerHTML = html;
        currentVersion = v;
        // Swapped-in markup arrives with untypeset .ps-math spans; the
        // DOMContentLoaded pass is long gone by then.
        renderMath(target);
    }

    function applyPending() {
        if (!pending) return;
        const { html, v } = pending;
        pending = null;
        swap(html, v);
    }

    function poll() {
        // One request at a time: a stalled tick must not stack up behind itself,
        // and a hidden tab is not worth a request at all.
        if (inFlight || document.hidden) return;
        inFlight = true;

        fetch(`${url}?v=${encodeURIComponent(currentVersion)}`, {
            headers: { 'Accept': 'application/json' },
        })
            .then(response => (response.ok ? response.json() : Promise.reject()))
            .then(data => {
                failures = 0;
                if (!data || !data.changed) return;

                // Swapping the DOM out from under an open modal detaches the
                // button it was launched from, so hold the update until the
                // page says it is safe. Only the newest one is worth keeping.
                if (canSwap && !canSwap()) {
                    pending = { html: data.html, v: data.v };
                    return;
                }
                swap(data.html, data.v);
            })
            .catch(() => {
                failures += 1;
            })
            .finally(() => {
                inFlight = false;
                schedule();
            });
    }

    function schedule() {
        clearTimeout(timer);
        // Back off on a run of failures so a server that went down is not hit by
        // every open tab every 10s; one success resets it.
        const delay = Math.min(intervalMs * Math.pow(2, failures), MAX_BACKOFF_MS);
        timer = setTimeout(poll, delay);
    }

    schedule();

    // The two moments the page is most likely to be stale, mirroring the exam
    // page's keep-alive: the tab was backgrounded (ticks skipped above), or the
    // connection dropped and came back.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });
    window.addEventListener('online', poll);

    return { applyPending };
};
