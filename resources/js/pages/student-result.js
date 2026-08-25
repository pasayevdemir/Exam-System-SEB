/** How long a finished student sees their result before being signed out. */
const COUNTDOWN_SECONDS = 20;

/**
 * Sign the student out a short while after they have seen their score.
 *
 * Exam machines are shared: the next candidate must not sit down in front of
 * someone else's open session. Either button cancels the countdown, because an
 * intent to keep using the account is a good enough reason to stay.
 */
export function start(doc = document) {
    const countdownEl = doc.getElementById('countdown');
    const logoutForm = doc.getElementById('logout-form');
    const logoutBtn = doc.getElementById('logout-btn');
    const continueLink = doc.getElementById('take-another-exam-link');

    if (!countdownEl || !logoutForm || !logoutBtn || !continueLink) {
        return;
    }

    let seconds = COUNTDOWN_SECONDS;
    let finished = false;
    let timer = null;

    function logout() {
        if (finished) return;

        finished = true;
        clearInterval(timer);
        logoutBtn.disabled = true;
        continueLink.classList.add('disabled');
        continueLink.setAttribute('aria-disabled', 'true');
        logoutForm.submit();
    }

    function cancel() {
        finished = true;
        clearInterval(timer);
    }

    timer = setInterval(() => {
        seconds -= 1;
        countdownEl.textContent = Math.max(seconds, 0);

        if (seconds <= 0) {
            logout();
        }
    }, 1000);

    logoutBtn.addEventListener('click', cancel);
    continueLink.addEventListener('click', cancel);
}
