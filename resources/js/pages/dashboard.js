/** How long the badge stays in its "copied" state before going back. */
const FEEDBACK_MS = 1200;

/**
 * Copy a generated student password to the clipboard, and say so.
 *
 * The badge is the only place a freshly issued password is shown, so the admin
 * has to be able to take it away without retyping it - and has to be able to
 * tell whether the copy worked before closing the page.
 */
export function start(doc = document) {
    doc.querySelectorAll('.copy-password').forEach(badge => {
        badge.addEventListener('click', () => {
            const icon = badge.querySelector('i');
            if (!icon || !navigator.clipboard) return;

            const restore = () => {
                badge.classList.remove('bg-success', 'text-white');
                badge.classList.add('bg-light', 'text-dark');
                icon.classList.remove('fa-check', 'fa-times');
                icon.classList.add('fa-copy');
            };

            navigator.clipboard.writeText(badge.dataset.password)
                .then(() => {
                    badge.classList.remove('bg-light', 'text-dark');
                    badge.classList.add('bg-success', 'text-white');
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check');
                    setTimeout(restore, FEEDBACK_MS);
                })
                .catch(() => {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-times');
                    setTimeout(restore, FEEDBACK_MS);
                });
        });
    });
}
