import { initQuestionTypeToggle } from '../shared/question-type.js';

/**
 * At least this many options, whatever the admin has filled in so far.
 * A question with one option is not a question.
 */
const MINIMUM_OPTIONS = 2;

function filledOptions(doc) {
    return Array.from(doc.querySelectorAll('.answer-text-input')).filter(
        input => input.value.trim() !== ''
    );
}

/**
 * Mark the first few option boxes required, and release the rest.
 *
 * The form renders a fixed number of boxes; the browser should insist on two
 * but not on all of them, and should keep insisting on any the admin has
 * started filling in.
 */
function initAnswerRequirements(doc) {
    const inputs = doc.querySelectorAll('.answer-text-input');

    function update() {
        const filled = filledOptions(doc).length;

        inputs.forEach((input, index) => {
            if (index < Math.max(MINIMUM_OPTIONS, filled)) {
                input.setAttribute('required', 'required');
            } else if (input.value.trim() === '') {
                input.removeAttribute('required');
            }
        });
    }

    inputs.forEach(input => {
        input.addEventListener('input', update);
        input.addEventListener('blur', update);
    });
}

/**
 * Refuse a submission the server would reject anyway, and drop the blank boxes.
 *
 * QuestionRequest re-does all of this server-side - this is here so the admin
 * hears about it without a round trip, not because it is the guard.
 */
function initSubmitGuard(doc) {
    const form = doc.querySelector('form');
    const fileUpload = doc.getElementById('file_upload');

    if (!form || !fileUpload) {
        return;
    }

    form.addEventListener('submit', event => {
        if (fileUpload.checked) {
            return;
        }

        const inputs = doc.querySelectorAll('.answer-text-input');
        const filled = filledOptions(doc);
        const marked = Array.from(doc.querySelectorAll('.correct-answer:checked'));

        if (filled.length < MINIMUM_OPTIONS) {
            event.preventDefault();
            alert('Please provide at least 2 answer options.');

            return;
        }

        // A mark on a box left blank points at nothing once the blanks are
        // dropped, so "something is marked" is not the same question as
        // "something that exists is marked".
        const marksOnFilledOptions = marked.filter(checkbox => {
            const option = inputs[parseInt(checkbox.value, 10)];

            return option && option.value.trim() !== '';
        });

        if (marked.length === 0 || marksOnFilledOptions.length === 0) {
            event.preventDefault();
            alert('Please select at least one correct answer for your question.');

            return;
        }

        // Disabled fields are not submitted, which is how the blanks are kept
        // out of the payload without renumbering anything on the way.
        inputs.forEach((input, index) => {
            if (input.value.trim() !== '') {
                return;
            }

            input.disabled = true;
            const checkbox = doc.querySelector(`.correct-answer[value="${index}"]`);
            if (checkbox) {
                checkbox.disabled = true;
            }
        });
    });
}

function initDeleteModal(doc) {
    const modalEl = doc.getElementById('deleteQuestionModal');
    const form = doc.getElementById('deleteQuestionForm');
    const label = doc.getElementById('questionToDelete');

    if (!modalEl || !form || !label) {
        return;
    }

    const modal = new window.bootstrap.Modal(modalEl);

    doc.querySelectorAll('.delete-question-btn').forEach(button => {
        button.addEventListener('click', () => {
            const text = button.closest('.border')?.querySelector('p')?.textContent.trim() ?? '';
            const shown = text.length > 100 ? `${text.substring(0, 100)}...` : text;

            label.textContent = `Question ${button.dataset.questionNumber}: ${shown}`;
            // data-action carries route('admin.delete-question', ...); the URL
            // used to be built here by hand, which is a route definition living
            // somewhere the router cannot see.
            form.action = button.dataset.action;

            modal.show();
        });
    });
}

export function start(doc = document) {
    initQuestionTypeToggle(doc);
    initAnswerRequirements(doc);
    initSubmitGuard(doc);
    initDeleteModal(doc);
}
