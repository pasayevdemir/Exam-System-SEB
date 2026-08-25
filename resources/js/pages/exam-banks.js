const LEVELS = ['easy', 'medium', 'hard'];

/**
 * Show how many questions the chosen bank actually holds at each difficulty,
 * and flag a quota that asks for more than that.
 *
 * The server refuses an over-quota generation anyway; this is so the admin sees
 * it while typing rather than when a student cannot start the exam.
 */
export function start(doc = document) {
    const bankSelect = doc.getElementById('question_bank_id');
    if (!bankSelect) return;

    const quotaInputs = {};
    const hints = {};

    LEVELS.forEach(level => {
        quotaInputs[level] = doc.getElementById(`quota_${level}`);
        hints[level] = doc.getElementById(`${level}-count-hint`);
    });

    function selectedCounts() {
        const option = bankSelect.options[bankSelect.selectedIndex];
        if (!option || !option.value) return null;

        return LEVELS.reduce((counts, level) => {
            counts[level] = parseInt(option.dataset[level] || '0', 10);

            return counts;
        }, {});
    }

    function refresh() {
        const counts = selectedCounts();

        LEVELS.forEach(level => {
            hints[level].textContent = counts ? `(${counts[level]} available)` : '';

            const input = quotaInputs[level];
            const exceeds = counts !== null && parseInt(input.value || '0', 10) > counts[level];

            input.classList.toggle('is-invalid', exceeds);
            input.classList.toggle('border-danger', exceeds);
        });
    }

    bankSelect.addEventListener('change', refresh);
    Object.values(quotaInputs).forEach(input => input.addEventListener('input', refresh));
}
