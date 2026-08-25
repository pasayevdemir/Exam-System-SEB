import { beforeEach, describe, expect, it } from 'vitest';
import { initQuestionTypeToggle } from '../../../resources/js/shared/question-type.js';

/**
 * The question form's type switch, which the create and edit blades each used to
 * carry their own copy of. One copy is the point of this file: a change here has
 * to hold for both pages, so both pages' behaviours are asserted together.
 */

function renderForm({ type = 'single', marked = [] } = {}) {
    document.body.innerHTML = `
        <input type="radio" id="single" name="question_type" ${type === 'single' ? 'checked' : ''}>
        <input type="radio" id="multiple" name="question_type" ${type === 'multiple' ? 'checked' : ''}>
        <input type="radio" id="file_upload" name="question_type" ${type === 'file_upload' ? 'checked' : ''}>

        <div id="mcqAnswersContainer">
            <div id="answersContainer">
                <input class="answer-text-input" name="answers[]" value="A">
                <input class="answer-text-input" name="answers[]" value="B">
            </div>
            <input type="checkbox" class="correct-answer" value="0" ${marked.includes(0) ? 'checked' : ''}>
            <input type="checkbox" class="correct-answer" value="1" ${marked.includes(1) ? 'checked' : ''}>
        </div>

        <div id="fileUploadSettings"></div>
    `;
}

const marks = () => Array.from(document.querySelectorAll('.correct-answer'));
const help = () => document.getElementById('correct-answers-help');

beforeEach(() => renderForm());

describe('a single choice question', function () {
    it('turns the marks into radios so only one can be set', function () {
        initQuestionTypeToggle(document);

        expect(marks().map(input => input.type)).toEqual(['radio', 'radio']);
        expect(marks().map(input => input.name)).toEqual(['correct_answers[]', 'correct_answers[]']);
    });

    it('says one answer is wanted', function () {
        initQuestionTypeToggle(document);

        expect(help().textContent).toContain('ONE');
    });
});

describe('a multiple choice question', function () {
    it('leaves the marks as checkboxes', function () {
        renderForm({ type: 'multiple' });
        initQuestionTypeToggle(document);

        expect(marks().map(input => input.type)).toEqual(['checkbox', 'checkbox']);
        expect(help().textContent).toContain('ALL');
    });
});

describe('a file upload question', function () {
    it('hides the options and shows the file settings', function () {
        renderForm({ type: 'file_upload' });
        initQuestionTypeToggle(document);

        expect(document.getElementById('mcqAnswersContainer').style.display).toBe('none');
        expect(document.getElementById('fileUploadSettings').style.display).toBe('block');
    });

    // A hidden field the browser insists on is a form that cannot be submitted
    // and does not say why.
    it('releases the required attribute on the hidden option boxes', function () {
        renderForm({ type: 'file_upload' });
        initQuestionTypeToggle(document);

        const required = Array.from(document.querySelectorAll('#answersContainer input'))
            .map(input => input.hasAttribute('required'));

        expect(required).toEqual([false, false]);
    });

    it('puts the requirement back when the type changes to a question', function () {
        renderForm({ type: 'file_upload' });
        initQuestionTypeToggle(document);

        document.getElementById('file_upload').checked = false;
        document.getElementById('single').checked = true;
        document.getElementById('single').dispatchEvent(new Event('change'));

        const required = Array.from(document.querySelectorAll('#answersContainer input'))
            .map(input => input.hasAttribute('required'));

        expect(required).toEqual([true, true]);
    });
});

describe('switching to single choice', function () {
    // Two marks carried into single choice would be rejected by the server, so
    // the form does not let the admin get there.
    it('clears marks the admin made while it was multiple choice', function () {
        renderForm({ type: 'multiple', marked: [0, 1] });
        initQuestionTypeToggle(document);

        document.getElementById('multiple').checked = false;
        document.getElementById('single').checked = true;
        document.getElementById('single').dispatchEvent(new Event('change'));

        expect(marks().map(input => input.checked)).toEqual([false, false]);
    });

    // Running the same clearing on the initial pass wiped the mark being
    // restored after a rejected submission, which is exactly when the admin
    // needs to see what they had chosen.
    it('keeps a mark that was already on the page when it loaded', function () {
        renderForm({ type: 'single', marked: [1] });
        initQuestionTypeToggle(document);

        expect(marks().map(input => input.checked)).toEqual([false, true]);
    });
});

it('leaves a form that is not the question form alone', function () {
    document.body.innerHTML = '<p>somewhere else entirely</p>';

    expect(() => initQuestionTypeToggle(document)).not.toThrow();
});
