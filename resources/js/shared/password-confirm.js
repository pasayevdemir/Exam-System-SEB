/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * The "type your password to confirm" dialog in front of a destructive action.
 *
 * One partial is included several times per page — once per kind of thing that
 * can be deleted — so the ids of the form, the item label and the password field
 * are all derived from the modal's own id. Reading them off the element rather
 * than off a server-rendered variable is what lets this run once for all of them
 * instead of once per include.
 */
export function initPasswordConfirmModals(doc = document) {
    doc.querySelectorAll('[data-password-confirm]').forEach(modal => {
        const form = doc.getElementById(`${modal.id}Form`);
        const item = doc.getElementById(`${modal.id}Item`);
        const password = doc.getElementById(`${modal.id}Password`);

        if (!form || !item || !password) {
            return;
        }

        modal.addEventListener('show.bs.modal', event => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            form.action = trigger.dataset.action || '';
            item.textContent = trigger.dataset.item || '';
            password.value = ''; // never carry a typed password between rows
        });

        modal.addEventListener('shown.bs.modal', () => password.focus());
    });
}
