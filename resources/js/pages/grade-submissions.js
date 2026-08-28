/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

function element(doc, tag, attributes = {}, text = null) {
    const node = doc.createElement(tag);

    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));

    if (text !== null) {
        node.textContent = text;
    }

    return node;
}

/**
 * Build the preview for one submitted file.
 *
 * Everything here is assembled as nodes rather than as an HTML string, and that
 * is the whole point: both the filename and the contents of a .txt come from a
 * student's upload. The previous version interpolated them into innerHTML, so a
 * file called `<img src=x onerror=...>.pdf`, or a .txt holding the same, ran
 * that script inside the admin's session the moment the admin looked at it.
 */
function renderPreview(doc, container, fileUrl, extension) {
    container.replaceChildren(element(doc, 'p', {}, 'Loading preview...'));

    if (IMAGE_EXTENSIONS.includes(extension)) {
        container.replaceChildren(element(doc, 'img', {
            src: fileUrl,
            class: 'img-fluid',
            alt: 'File preview',
            style: 'max-height: 500px;',
        }));

        return;
    }

    if (extension === 'pdf') {
        const note = element(doc, 'p', { class: 'mt-3 text-muted' }, 'If the PDF does not display, ');
        const link = element(doc, 'a', { href: fileUrl, target: '_blank' }, 'click here to open it');
        note.appendChild(link);
        note.appendChild(doc.createTextNode('.'));

        container.replaceChildren(
            element(doc, 'embed', {
                src: fileUrl,
                type: 'application/pdf',
                width: '100%',
                height: '500px',
            }),
            note
        );

        return;
    }

    if (extension === 'txt') {
        fetch(fileUrl)
            .then(response => response.text())
            .then(text => {
                const wrapper = element(doc, 'div', { class: 'text-start' });
                wrapper.appendChild(element(doc, 'pre', {
                    class: 'bg-light p-3 rounded',
                    style: 'max-height: 400px; overflow-y: auto;',
                }, text));

                container.replaceChildren(wrapper);
            })
            .catch(() => {
                container.replaceChildren(element(doc, 'p', { class: 'text-muted' },
                    'Cannot preview this file type. Please download to view.'));
            });

        return;
    }

    const fallback = element(doc, 'div', { class: 'text-center py-5' });
    fallback.appendChild(element(doc, 'i', { class: 'fas fa-file-alt fa-3x text-muted mb-3' }));
    fallback.appendChild(element(doc, 'p', { class: 'text-muted' },
        `Preview not available for this file type (${extension.toUpperCase()}).`));
    fallback.appendChild(element(doc, 'p', {}, 'Please download the file to view its contents.'));

    container.replaceChildren(fallback);
}

function initFilePreview(doc) {
    const modalEl = doc.getElementById('filePreviewModal');
    const content = doc.getElementById('filePreviewContent');
    const downloadBtn = doc.getElementById('downloadFileBtn');
    const title = doc.getElementById('filePreviewModalLabel');

    if (!modalEl || !content || !downloadBtn || !title) {
        return;
    }

    const modal = new window.bootstrap.Modal(modalEl);

    doc.querySelectorAll('.view-file-btn').forEach(button => {
        button.addEventListener('click', () => {
            const fileUrl = button.dataset.fileUrl;
            const fileName = button.dataset.fileName ?? '';
            const extension = fileName.split('.').pop().toLowerCase();

            title.replaceChildren(
                element(doc, 'i', { class: 'fas fa-file me-2' }),
                doc.createTextNode(fileName)
            );
            downloadBtn.href = fileUrl;

            renderPreview(doc, content, fileUrl, extension);
            modal.show();
        });
    });
}

/**
 * Marking is a per-submission form, so an admin working down a page of them
 * needs to see which one they just sent.
 */
function initSubmitFeedback(doc) {
    doc.querySelectorAll('.grading-form').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;

            button.replaceChildren(
                element(doc, 'i', { class: 'fas fa-spinner fa-spin me-1' }),
                doc.createTextNode('Saving...')
            );
            button.disabled = true;
        });
    });
}

export function start(doc = document) {
    initFilePreview(doc);
    initSubmitFeedback(doc);
}
