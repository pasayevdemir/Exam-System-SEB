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
export function renderMath(root = document) {
    root.querySelectorAll('.ps-math:not(.ps-math-done)').forEach(el => {
        katex.render(el.textContent, el, {
            displayMode: el.dataset.display === '1',
            throwOnError: false,
        });
        el.classList.add('ps-math-done');
    });
}
