import { createLivePoll } from '../live-poll.js';

/**
 * The exam list a student lands on: the "are you ready to start" dialog, and a
 * poll that keeps the cards in step with the admin side.
 *
 * The two are connected. Replacing the cards while the dialog is up would
 * detach the very button it was opened from, so the poll asks before swapping
 * and the dialog releases the held update when it closes.
 */
export function start(doc = document) {
    const modalEl = doc.getElementById('startExamModal');
    const listEl = doc.getElementById('examListLive');

    // Declared up front so the poll can consult it even if the modal is somehow
    // absent and its wiring is skipped.
    let modalOpen = false;
    let poll = null;

    if (modalEl) {
        const nameEl = doc.getElementById('startExamModalExamName');
        const timeLimitEl = doc.getElementById('startExamModalTimeLimit');
        const confirmBtn = doc.getElementById('startExamModalConfirm');

        modalEl.addEventListener('show.bs.modal', event => {
            modalOpen = true;

            const button = event.relatedTarget;
            nameEl.textContent = button.dataset.name;
            confirmBtn.dataset.url = button.dataset.url;

            const timeLimit = button.dataset.timeLimit;

            if (timeLimit) {
                timeLimitEl.textContent = `Vaxt limiti: ${timeLimit} dəqiqədir. `
                    + 'Vaxt bitdikdə imtahan avtomatik təqdim olunacaq.';
                timeLimitEl.classList.remove('d-none');
            } else {
                timeLimitEl.classList.add('d-none');
            }
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            modalOpen = false;
            // Only now is it safe to replace the cards.
            if (poll) poll.applyPending();
        });

        confirmBtn.addEventListener('click', () => {
            // Starting is one-shot: a double click would fire two generate requests.
            confirmBtn.disabled = true;
            window.location.href = confirmBtn.dataset.url;
        });
    }

    // Activating an exam, renaming it, changing its banks or allowing a retake
    // all show up here on their own.
    if (listEl) {
        poll = createLivePoll({
            url: listEl.dataset.url,
            version: listEl.dataset.v,
            target: listEl,
            canSwap: () => !modalOpen,
        });
    }
}
