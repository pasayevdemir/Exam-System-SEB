/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * The single / multiple / file-upload switch on the question form.
 *
 * Creating a question and editing one are the same form, and each blade used to
 * carry its own copy of this — near-identical, so a fix to one never reached the
 * other. They had already drifted: only the create copy told the admin how many
 * options to mark, and only the create copy cleared the marks when switching to
 * single choice.
 *
 * Both behaviours are kept, with one correction. Clearing now happens when the
 * admin actually changes the type, not on the initial pass — running it at load
 * wiped the marks that were being restored after a rejected submission.
 */
export function initQuestionTypeToggle(doc = document) {
    const single = doc.getElementById('single');
    const multiple = doc.getElementById('multiple');
    const fileUpload = doc.getElementById('file_upload');
    const mcqContainer = doc.getElementById('mcqAnswersContainer');
    const fileSettings = doc.getElementById('fileUploadSettings');

    if (!single || !multiple || !fileUpload || !mcqContainer || !fileSettings) {
        return;
    }

    function setAnswersRequired(required) {
        doc.querySelectorAll('#answersContainer input').forEach(input => {
            if (required) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }
        });
    }

    function updateHelpText() {
        doc.getElementById('correct-answers-help')?.remove();

        const help = doc.createElement('small');
        help.id = 'correct-answers-help';
        help.className = 'text-muted';
        help.textContent = single.checked
            ? 'Select ONE correct answer'
            : 'Select ALL correct answers (you can select multiple options)';

        mcqContainer.appendChild(help);
    }

    /**
     * A single-choice question takes exactly one mark, so the same inputs are
     * radios there and checkboxes otherwise. They share a name either way: the
     * server reads positions out of correct_answers[] regardless.
     */
    function updateCheckboxBehavior(clearSelections) {
        doc.querySelectorAll('.correct-answer').forEach(input => {
            input.type = single.checked ? 'radio' : 'checkbox';
            input.name = 'correct_answers[]';

            // Two marks carried into single choice would be rejected by the
            // server, so the form does not let the admin get there.
            if (clearSelections && single.checked) {
                input.checked = false;
            }
        });

        updateHelpText();
    }

    function updateQuestionTypeDisplay(clearSelections) {
        const isFileUpload = fileUpload.checked;

        mcqContainer.style.display = isFileUpload ? 'none' : 'block';
        fileSettings.style.display = isFileUpload ? 'block' : 'none';
        setAnswersRequired(! isFileUpload);

        if (! isFileUpload) {
            updateCheckboxBehavior(clearSelections);
        }
    }

    [single, multiple, fileUpload].forEach(radio => {
        radio.addEventListener('change', () => updateQuestionTypeDisplay(true));
    });

    updateQuestionTypeDisplay(false);
}
