/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

/**
 * The exam countdown and the auto-submit it triggers.
 *
 * The countdown is driven off a fixed deadline rather than by subtracting 1 per
 * tick: a throttled or skipped interval then costs nothing, because each tick
 * re-derives the remaining time instead of accumulating whatever the browser
 * actually delivered. The deadline is anchored once from the server's reading,
 * so changing the machine clock mid-exam has no effect either.
 */

// The server is the only authority on when this attempt ends, so any response
// carrying its reading re-anchors the deadline. Small gaps are just request
// latency and are ignored - correcting on those would make the visible clock
// jitter by a second on every autosave.
const CLOCK_SYNC_TOLERANCE_SECONDS = 2;

const AUTO_SUBMIT_BUFFER_SECONDS = 3;

export function formatTime(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    const pad = n => String(n).padStart(2, '0');

    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
}

export function createTimer({
    remainingSeconds,
    gracePeriodSeconds,
    clock,
    finalizeAnswers,
    submitForm,
    doc = document,
}) {
    const timerEl = doc.getElementById('exam-timer');
    const timerBar = doc.getElementById('timer-bar');
    const autoSubmitInput = doc.getElementById('auto_submit');

    let deadline = Date.now() + remainingSeconds * 1000;
    let timerInterval = null;
    let autoSubmitted = false;

    function renderTimer() {
        timerEl.textContent = formatTime(remainingSeconds);

        timerBar.classList.remove('ps-timer-warning', 'ps-timer-danger');
        if (remainingSeconds <= 60) {
            timerBar.classList.add('ps-timer-danger');
        } else if (remainingSeconds <= 300) {
            timerBar.classList.add('ps-timer-warning');
        }
    }

    // The server will not treat this attempt as expired until expires_at plus
    // its own grace period (ExamAttempt::isExpired()), and only an attempt it
    // agrees is over gets scored from the frozen autosave snapshot instead of
    // this request's body. Waiting out the grace period (plus a small buffer
    // for the request's own transit time) is what puts an expiry-triggered
    // submit on that path, so answers cannot be edited or replayed in the
    // submit body after time is up.
    function autoSubmitExam() {
        if (autoSubmitted) return Promise.resolve();
        autoSubmitted = true;
        clearInterval(timerInterval);

        autoSubmitInput.value = '1';

        const timeUpModalEl = doc.getElementById('timeUpModal');
        if (timeUpModalEl && window.bootstrap) {
            new window.bootstrap.Modal(timeUpModalEl).show();
        }

        const autoSubmitDelaySeconds = gracePeriodSeconds + AUTO_SUBMIT_BUFFER_SECONDS;
        const countdownEl = doc.getElementById('autoSubmitCountdown');
        let countdown = autoSubmitDelaySeconds;

        if (countdownEl) {
            countdownEl.textContent = countdown;
        }

        const countdownInterval = setInterval(function () {
            countdown--;
            if (countdownEl) {
                countdownEl.textContent = Math.max(countdown, 0);
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);

        // Submit only once BOTH are true: the visible countdown has run out
        // (the server-required grace period) AND every current answer has had
        // its fast, bounded save attempt settle. finalizeAnswers is capped at
        // ~5.3s worst case, comfortably under the countdown, so in practice the
        // countdown is what the student actually sees finish - this Promise.all
        // just stops a slow save from ever being silently cut off instead of
        // gambling that the countdown alone was long enough.
        const gracePeriodElapsed = new Promise(resolve => setTimeout(resolve, autoSubmitDelaySeconds * 1000));

        return Promise.all([gracePeriodElapsed, finalizeAnswers()]).then(function () {
            submitForm();
        });
    }

    function tick() {
        remainingSeconds = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        renderTimer();

        if (remainingSeconds <= 0) {
            autoSubmitExam();
        }
    }

    function start() {
        clock.onSync(function (serverSeconds) {
            if (typeof serverSeconds !== 'number' || autoSubmitted) return;
            if (Math.abs(serverSeconds - remainingSeconds) <= CLOCK_SYNC_TOLERANCE_SECONDS) return;

            deadline = Date.now() + serverSeconds * 1000;
            tick();
        });

        renderTimer();

        if (remainingSeconds <= 0) {
            autoSubmitExam();
        } else {
            timerInterval = setInterval(tick, 1000);
        }
    }

    return {
        start,
        tick,
        autoSubmitExam,
        remaining: () => remainingSeconds,
        hasAutoSubmitted: () => autoSubmitted,
    };
}
