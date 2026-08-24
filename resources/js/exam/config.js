/**
 * The exam page's server-side values, handed over as one JSON block rather than
 * interpolated into executable script. Blade escapes `<` inside @json, so a
 * question containing a closing script tag cannot break out of the block.
 *
 * Returns null on any page that is not the exam page, which is how index.js
 * knows to do nothing.
 */
export function readExamConfig(doc = document) {
    const el = doc.getElementById('exam-config');

    if (!el) {
        return null;
    }

    try {
        return JSON.parse(el.textContent);
    } catch (e) {
        return null;
    }
}
