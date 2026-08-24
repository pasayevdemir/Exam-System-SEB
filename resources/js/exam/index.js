import { readExamConfig } from './config.js';
import { createCsrf } from './csrf.js';
import { createServerClock } from './clock.js';
import { createProgress } from './progress.js';
import { bindFileUploads } from './file-uploads.js';
import { createAutosave } from './autosave.js';
import { createTelemetry } from './telemetry.js';
import { createKeepAlive } from './keep-alive.js';
import { createTimer } from './timer.js';

const QUEUE_FLUSH_INTERVAL_MS = 15000;

/**
 * Wires the exam page together. Everything server-side arrives through the
 * #exam-config JSON block; no module reads Blade output of its own.
 */
export function start(doc = document, win = window) {
    const config = readExamConfig(doc);

    if (!config) {
        return null;
    }

    const csrf = createCsrf(doc);
    const clock = createServerClock();

    const progress = createProgress({ totalQuestions: config.totalQuestions, doc });
    progress.bindMapButtons();

    const autosave = createAutosave({
        url: config.autosaveUrl,
        queueKey: config.queueKey,
        csrf,
        clock,
        doc,
    });

    bindFileUploads({ onChange: progress.update, doc });

    doc.querySelectorAll('.question-input:not(.file-input)').forEach(input => {
        input.addEventListener('change', function () {
            progress.update();
            autosave.autosaveAnswer(this.dataset.question);
        });
    });

    win.addEventListener('online', autosave.flushQueue);
    setInterval(autosave.flushQueue, QUEUE_FLUSH_INTERVAL_MS);
    autosave.flushQueue();

    createTelemetry({ url: config.eventUrl, csrf, doc, win }).bind();
    createKeepAlive({ url: config.keepAliveUrl, csrf, clock, doc, win }).bind();

    const form = doc.getElementById('examForm');
    const confirmSubmitBtn = doc.getElementById('confirmSubmitBtn');

    if (confirmSubmitBtn) {
        confirmSubmitBtn.addEventListener('click', function () {
            const modalEl = doc.getElementById('confirmSubmitModal');
            if (modalEl && win.bootstrap) {
                win.bootstrap.Modal.getInstance(modalEl).hide();
            }
            form.submit();
        });
    }

    progress.update();

    let timer = null;

    if (config.timed) {
        timer = createTimer({
            remainingSeconds: config.remainingSeconds,
            gracePeriodSeconds: config.gracePeriodSeconds,
            clock,
            finalizeAnswers: autosave.finalizeAllAnswers,
            submitForm: () => form.submit(),
            doc,
        });

        timer.start();
    }

    return { progress, autosave, clock, timer };
}
