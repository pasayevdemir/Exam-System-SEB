/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import './bootstrap';

// Bootstrap's own JS bundle (dropdowns, modals, alert dismiss via
// data-bs-dismiss, etc.) — auto-init relies on `bootstrap` being global,
// same as when it was loaded from the CDN <script> tag.
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import { renderMath } from './math.js';
import { initConfirmSubmit } from './shared/confirm-submit.js';
import { initPasswordConfirmModals } from './shared/password-confirm.js';

/**
 * Page modules, keyed by the data-page attribute the layout renders.
 *
 * Static keys with dynamic imports rather than a computed import path: Vite can
 * only code-split what it can see, so this is what keeps each page's bundle off
 * every other page while still being one @vite entry.
 */
const pageModules = {
    'exam': () => import('./exam/index.js'),
    'bank-questions': () => import('./pages/bank-questions.js'),
    'edit-question': () => import('./pages/edit-question.js'),
    'grade-submissions': () => import('./pages/grade-submissions.js'),
    'exam-banks': () => import('./pages/exam-banks.js'),
    'exam-results': () => import('./pages/exam-results.js'),
    'dashboard': () => import('./pages/dashboard.js'),
    'student-exams': () => import('./pages/student-exams.js'),
    'student-result': () => import('./pages/student-result.js'),
    'my-results': () => import('./pages/my-results.js'),
};

function announceScriptFailure() {
    if (document.getElementById('psScriptFailure')) return;

    const banner = document.createElement('div');
    banner.id = 'psScriptFailure';
    banner.className = 'alert alert-danger m-3';
    banner.setAttribute('role', 'alert');
    banner.textContent = 'Səhifə tam yüklənmədi — cavablarınız avtomatik saxlanılmaya bilər. '
        + 'Zəhmət olmasa səhifəni yeniləyin.';

    document.body.prepend(banner);
}

document.addEventListener('DOMContentLoaded', () => {
    renderMath();

    // Not page modules: both wire up markup that appears on whichever pages
    // happen to carry it, and find nothing to do on the rest.
    initPasswordConfirmModals();
    initConfirmSubmit();

    const page = document.body.dataset.page;

    if (page && pageModules[page]) {
        pageModules[page]()
            .then(module => module.start())
            .catch(error => {
                // On the exam page this means no autosave, no countdown and no
                // auto-submit. Failing silently would let a student sit a whole
                // exam that never saves anything, so say so loudly enough to act
                // on rather than only logging it.
                console.error(`Page module "${page}" failed to load`, error);
                announceScriptFailure();
            });
    }
});
