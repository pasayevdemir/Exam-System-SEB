/**
 * Answered-count, progress bar, question map and the submit warning.
 *
 * All four read the same answered set, which is why they live together: the map
 * highlight and the counter cannot disagree about what counts as answered, and
 * restored autosave drafts and uploaded files are covered by both without a
 * second source of truth.
 */
export function createProgress({ totalQuestions, doc = document }) {
    const answeredCountSpan = doc.getElementById('answered-count');
    const modalAnsweredCountSpan = doc.getElementById('modal-answered-count');
    const modalUnansweredCountSpan = doc.getElementById('modal-unanswered-count');
    const modalUnansweredWarning = doc.getElementById('modal-unanswered-warning');
    const progressBar = doc.getElementById('progress-bar');
    const submitWarning = doc.getElementById('submit-warning');
    const mapButtons = Array.from(doc.querySelectorAll('.ps-qmap-btn'));

    function answeredQuestionIds() {
        const answered = new Set();

        doc.querySelectorAll('.question-input:checked').forEach(input => {
            answered.add(input.dataset.question);
        });

        doc.querySelectorAll('.file-input').forEach(input => {
            if (input.files && input.files.length > 0) {
                answered.add(input.dataset.question);
            }
        });

        return answered;
    }

    function update() {
        const answered = answeredQuestionIds();

        mapButtons.forEach(button => {
            button.classList.toggle('is-answered', answered.has(button.dataset.question));
        });

        const answeredCount = answered.size;
        const percentage = (answeredCount / totalQuestions) * 100;

        answeredCountSpan.textContent = answeredCount;
        modalAnsweredCountSpan.textContent = answeredCount;
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);

        // Submitting is always allowed - a student who wants to hand in a partly
        // blank paper may, exactly like on paper. The count is surfaced here and
        // again in the confirm modal so it is never an accident.
        const unansweredCount = totalQuestions - answeredCount;

        modalUnansweredCountSpan.textContent = unansweredCount;
        modalUnansweredWarning.classList.toggle('d-none', unansweredCount === 0);

        if (unansweredCount === 0) {
            submitWarning.textContent = 'All questions answered. You can now submit the exam.';
            submitWarning.classList.remove('text-muted');
            submitWarning.classList.add('text-success');
        } else {
            submitWarning.textContent = unansweredCount + ' question(s) unanswered - you can still submit, but they will score nothing.';
            submitWarning.classList.remove('text-success');
            submitWarning.classList.add('text-muted');
        }
    }

    function bindMapButtons() {
        mapButtons.forEach(button => {
            button.addEventListener('click', function () {
                const card = doc.getElementById('question-card-' + this.dataset.question);

                if (!card) {
                    return;
                }

                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                card.classList.add('ps-question-focus');
                setTimeout(() => card.classList.remove('ps-question-focus'), 1200);
            });
        });
    }

    return { update, bindMapButtons, answeredQuestionIds };
}
