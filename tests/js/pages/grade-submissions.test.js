/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { start } from '../../../resources/js/pages/grade-submissions.js';

/**
 * The file preview an admin opens to mark a submission by hand.
 *
 * Both the filename and the contents of a .txt come from a student's upload, and
 * the previous version interpolated both into innerHTML. A file called
 * `<img src=x onerror=...>.txt`, or a .txt holding the same, therefore ran that
 * script inside the admin's session - a stored XSS reachable by anyone who can
 * sit an exam.
 */

const PAYLOAD = '<img src=x onerror="document.body.dataset.pwned=1">';

function renderPage({ fileName = 'proof.pdf', fileUrl = '/examadmin/submission/1/download' } = {}) {
    document.body.innerHTML = `
        <button class="view-file-btn" data-file-url="${fileUrl}" data-file-name="${fileName.replace(/"/g, '&quot;')}">View</button>
        <div id="filePreviewModal">
            <h5 id="filePreviewModalLabel"></h5>
            <div id="filePreviewContent"></div>
            <a id="downloadFileBtn"></a>
        </div>
        <form class="grading-form"><button type="submit">Save</button></form>
    `;

    window.bootstrap = { Modal: class { show() {} } };
}

const openPreview = () => document.querySelector('.view-file-btn').click();

beforeEach(() => renderPage());

describe('the preview title', function () {
    it('shows the filename as text, not as markup', function () {
        renderPage({ fileName: `${PAYLOAD}.pdf` });
        start(document);
        openPreview();

        const title = document.getElementById('filePreviewModalLabel');

        expect(title.textContent).toContain(PAYLOAD);
        expect(title.querySelector('img')).toBeNull();
        expect(document.body.dataset.pwned).toBeUndefined();
    });

    it('still carries the file icon', function () {
        start(document);
        openPreview();

        expect(document.querySelector('#filePreviewModalLabel i.fa-file')).not.toBeNull();
    });
});

describe('a text file preview', function () {
    it('shows the contents as text, not as markup', async function () {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ text: () => Promise.resolve(PAYLOAD) })));

        renderPage({ fileName: 'notes.txt' });
        start(document);
        openPreview();

        await vi.waitFor(() => {
            expect(document.querySelector('#filePreviewContent pre')).not.toBeNull();
        });

        const pre = document.querySelector('#filePreviewContent pre');

        expect(pre.textContent).toBe(PAYLOAD);
        expect(pre.querySelector('img')).toBeNull();
        expect(document.body.dataset.pwned).toBeUndefined();
    });

    it('says so when the file cannot be read', async function () {
        vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new Error('gone'))));

        renderPage({ fileName: 'notes.txt' });
        start(document);
        openPreview();

        await vi.waitFor(() => {
            expect(document.getElementById('filePreviewContent').textContent).toContain('Cannot preview');
        });
    });
});

describe('other file types', function () {
    it('embeds a pdf and points the download button at it', function () {
        start(document);
        openPreview();

        expect(document.querySelector('#filePreviewContent embed').getAttribute('src'))
            .toBe('/examadmin/submission/1/download');
        expect(document.getElementById('downloadFileBtn').getAttribute('href'))
            .toBe('/examadmin/submission/1/download');
    });

    it('shows an image inline', function () {
        renderPage({ fileName: 'scan.PNG' });
        start(document);
        openPreview();

        expect(document.querySelector('#filePreviewContent img')).not.toBeNull();
    });

    it('offers a download for anything it cannot render', function () {
        renderPage({ fileName: 'archive.zip' });
        start(document);
        openPreview();

        expect(document.getElementById('filePreviewContent').textContent).toContain('ZIP');
    });
});

// An admin marking a page of submissions needs to see which one they just sent.
it('marks the grading button as saving once submitted', function () {
    start(document);

    const form = document.querySelector('.grading-form');
    form.dispatchEvent(new Event('submit'));

    const button = form.querySelector('button');

    expect(button.disabled).toBe(true);
    expect(button.textContent).toContain('Saving');
});
