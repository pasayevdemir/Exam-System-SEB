/**
 * Background autosave for MCQ selections, plus the queue that survives a
 * connection dropping out from under it.
 *
 * The values sent are positions in this attempt's pinned option order, never
 * answer ids, so one question's answer can never be submitted for another.
 */
export function createAutosave({ url, queueKey, csrf, clock, doc = document }) {
    const autosaveStatus = doc.getElementById('autosave-status');
    const connectionDot = doc.getElementById('connection-dot');
    const connectionLabel = doc.getElementById('connection-label');

    let autosaveSeq = 0;
    const questionAttemptSeq = {};

    // Questions with a live save in progress. A queued entry is by definition
    // older than whatever is being saved now, so flushQueue steps over these
    // rather than racing them - see flushQueue for what goes wrong otherwise.
    const inFlight = new Set();

    function setAutosaveStatus(text, cls) {
        autosaveStatus.textContent = text;
        autosaveStatus.className = 'small mt-1 ' + cls;
    }

    // sessionStorage, not localStorage - scoped to this tab/attempt, not meant
    // to survive across devices or outlive the session.
    function readQueue() {
        try {
            return JSON.parse(sessionStorage.getItem(queueKey) || '[]');
        } catch (e) {
            return [];
        }
    }

    function queueAnswer(payload) {
        const queue = readQueue().filter(item => item.question_id !== payload.question_id);
        queue.push(payload);
        sessionStorage.setItem(queueKey, JSON.stringify(queue));
    }

    function dequeueAnswer(questionId) {
        const queue = readQueue().filter(item => item.question_id !== questionId);
        sessionStorage.setItem(queueKey, JSON.stringify(queue));
    }

    function updateConnectionIndicator(state) {
        // state omitted -> derive from whether anything is still queued
        if (state === undefined) {
            state = readQueue().length > 0 ? 'offline' : 'ok';
        }

        const styles = {
            ok: ['bg-success', 'text-muted', 'Bağlantı var'],
            retrying: ['bg-warning', 'text-warning', 'Yenidən cəhd edilir…'],
            offline: ['bg-danger', 'text-danger', readQueue().length + ' cavab növbədə — yenidən cəhd ediləcək'],
        };
        const [dotClass, labelClass, label] = styles[state];

        connectionDot.className = 'd-inline-block rounded-circle ' + dotClass;
        connectionDot.style.width = '8px';
        connectionDot.style.height = '8px';
        connectionLabel.className = labelClass;
        connectionLabel.textContent = label;
    }

    // timeoutMs is optional and only used by the fast last-ditch path below -
    // routine autosave calls this with no timeout. fetch() alone never times out
    // on a merely-slow connection, so without this a single stalled request
    // could sit open indefinitely.
    function postAnswer(payload, timeoutMs) {
        const controller = new AbortController();
        const timeoutId = timeoutMs ? setTimeout(() => controller.abort(), timeoutMs) : null;

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf.token(),
            },
            body: JSON.stringify(payload),
            signal: controller.signal,
        }).then(response => {
            if (!response.ok) {
                const err = new Error('autosave failed: ' + response.status);
                err.status = response.status;
                throw err;
            }

            return response.json().catch(() => null);
        }).then(data => {
            if (data) {
                clock.report(data.remaining_seconds);
            }
        }).finally(() => {
            if (timeoutId) clearTimeout(timeoutId);
        });
    }

    // A 409 (time limit reached) is a definitive answer, not a transient
    // failure - retrying or queuing it would just be futile once the attempt
    // is over.
    //
    // options.maxAttempts/delayMs/timeoutMs let call sites tune how patient
    // vs urgent a retry sequence is. Default (no options): 3 attempts, 1s/2s
    // exponential backoff, no per-request timeout - polite background
    // autosave. The last-ditch save right before an expiry-triggered
    // auto-submit needs to be more urgent instead, since it is racing a fixed
    // countdown (see postAnswerWithFastRetry / finalizeAllAnswers).
    function postAnswerWithRetry(payload, options) {
        options = options || {};
        const maxAttempts = options.maxAttempts || 3;
        const timeoutMs = options.timeoutMs;
        const delayMs = options.delayMs || (attempt => Math.pow(2, attempt) * 1000);

        function attempt(n) {
            return postAnswer(payload, timeoutMs).catch(err => {
                if (err.status === 409 || n >= maxAttempts - 1) throw err;

                return new Promise(resolve => setTimeout(resolve, delayMs(n)))
                    .then(() => attempt(n + 1));
            });
        }

        return attempt(0);
    }

    // Used only right before an expiry-triggered auto-submit, where we are
    // racing the fixed grace-period countdown instead of just being polite in
    // the background. 2 attempts, capped at 2.5s each via the abort timeout
    // in postAnswer and 300ms apart - worst case ~5.3s, deliberately kept
    // well under the countdown so it is never the thing that decides when we
    // submit.
    function postAnswerWithFastRetry(payload) {
        return postAnswerWithRetry(payload, { maxAttempts: 2, timeoutMs: 2500, delayMs: () => 300 });
    }

    // Builds the payload from an already-queried list of a question's inputs -
    // callers own the query, since a single question (autosaveAnswer) and every
    // question at once (finalizeAllAnswers) need different DOM access patterns
    // to stay efficient.
    function answerPayloadFromInputs(questionId, inputs) {
        const checked = inputs.filter(i => i.checked).map(i => i.value);

        return { question_id: questionId, answer_indexes: checked };
    }

    function autosaveAnswer(questionId) {
        const mySeq = ++autosaveSeq;
        const myQSeq = questionAttemptSeq[questionId] = (questionAttemptSeq[questionId] || 0) + 1;
        const inputs = doc.querySelectorAll('.question-input[data-question="' + questionId + '"]:not(.file-input)');

        if (inputs.length === 0) {
            return Promise.resolve();
        }

        const payload = answerPayloadFromInputs(questionId, Array.from(inputs));

        setAutosaveStatus('Saxlanılır...', 'text-muted');
        updateConnectionIndicator('retrying');
        inFlight.add(questionId);

        return postAnswerWithRetry(payload)
            .then(() => {
                // A newer attempt for this same question already superseded this
                // one - let its own resolution be the one that updates state.
                if (questionAttemptSeq[questionId] !== myQSeq) return;

                dequeueAnswer(questionId);
                if (mySeq === autosaveSeq) setAutosaveStatus('Saxlanıldı', 'text-success');
                updateConnectionIndicator();
            })
            .catch(err => {
                if (questionAttemptSeq[questionId] !== myQSeq) return;

                if (err && err.status === 409) {
                    if (mySeq === autosaveSeq) setAutosaveStatus('Vaxt limiti bitdi', 'text-danger');
                    updateConnectionIndicator();

                    return;
                }

                queueAnswer(payload);
                if (mySeq === autosaveSeq) {
                    setAutosaveStatus('Saxlanmadı — növbəyə alındı, avtomatik yenidən cəhd ediləcək', 'text-danger');
                }
                updateConnectionIndicator('offline');
            })
            .finally(() => {
                // Only the newest attempt for this question clears the flag; an
                // older one finishing late must not open the door for a flush.
                if (questionAttemptSeq[questionId] === myQSeq) {
                    inFlight.delete(questionId);
                }
            });
    }

    // Retries whatever is sitting in the queue - called on reconnect, on a
    // periodic timer, and on page load (a reload can leave a queue behind).
    function flushQueue() {
        // Skipping questions mid-save is the point: re-sending the queued value
        // alongside a newer one lets the stale request land last and win on the
        // server, and the answer the student actually chose is then neither
        // saved nor left in the queue. If that live save fails it re-queues
        // itself, so nothing is dropped by waiting.
        const queue = readQueue().filter(payload => !inFlight.has(payload.question_id));

        if (queue.length === 0) {
            updateConnectionIndicator();

            return Promise.resolve();
        }

        updateConnectionIndicator('retrying');

        const sends = queue.map(payload => {
            const myQSeq = questionAttemptSeq[payload.question_id] =
                (questionAttemptSeq[payload.question_id] || 0) + 1;

            return postAnswerWithRetry(payload)
                .then(() => {
                    if (questionAttemptSeq[payload.question_id] !== myQSeq) return;

                    dequeueAnswer(payload.question_id);
                    updateConnectionIndicator();
                })
                .catch(err => {
                    if (questionAttemptSeq[payload.question_id] !== myQSeq) return;

                    // A 409 will never succeed by retrying later - drop it so the
                    // queue does not retry forever against a closed attempt.
                    if (err && err.status === 409) {
                        dequeueAnswer(payload.question_id);
                        updateConnectionIndicator();

                        return;
                    }

                    updateConnectionIndicator('offline');
                });
        });

        return Promise.allSettled(sends).then(() => undefined);
    }

    // Right before an expiry-triggered auto-submit, resend every currently
    // selected MCQ answer - not just whatever already failed and landed in
    // the queue. Once the server considers the attempt expired it scores
    // only from these saved drafts and ignores the submit request's own
    // body, so anything not durably saved by then (including a change still
    // silently mid-retry in the background, which the queue alone would not
    // catch) is simply lost. Fired in parallel per question, so the total
    // wait is bounded by the single slowest one (~5.3s, see
    // postAnswerWithFastRetry), not by how many questions there are.
    function finalizeAllAnswers() {
        const grouped = new Map();

        doc.querySelectorAll('.question-input:not(.file-input)').forEach(input => {
            const qid = input.dataset.question;
            if (!grouped.has(qid)) grouped.set(qid, []);
            grouped.get(qid).push(input);
        });

        if (grouped.size === 0) {
            return Promise.resolve();
        }

        updateConnectionIndicator('retrying');

        const sends = Array.from(grouped, ([questionId, inputs]) => {
            const payload = answerPayloadFromInputs(questionId, inputs);
            const myQSeq = questionAttemptSeq[questionId] = (questionAttemptSeq[questionId] || 0) + 1;

            return postAnswerWithFastRetry(payload)
                .then(() => {
                    if (questionAttemptSeq[questionId] !== myQSeq) return;

                    dequeueAnswer(questionId);
                })
                .catch(() => {
                    // Left queued (or a 409 confirms the deadline is already
                    // closed either way) - routine flush call sites would
                    // keep retrying it, but the submit is going ahead now
                    // regardless since we cannot hold the exam open forever.
                });
        });

        return Promise.allSettled(sends).then(() => updateConnectionIndicator());
    }

    return {
        autosaveAnswer,
        flushQueue,
        finalizeAllAnswers,
        updateConnectionIndicator,
        readQueue,
    };
}
