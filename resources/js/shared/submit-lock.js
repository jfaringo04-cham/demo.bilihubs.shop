/**
 * Prevents a form from being sent twice.
 *
 * The registration forms upload documents and trigger notification mail, so
 * the request stays pending long enough for an impatient second click. The
 * backend unique e-mail rule would then reject the duplicate POST even though
 * the first one succeeded, which is what produced the misleading
 * "The email has already been taken." message.
 *
 * This only guards the browser side; the server keeps validating uniqueness.
 */
const initSubmitLock = (formId, busyLabel = 'Submitting...') => {
    const form = document.getElementById(formId);
    if (!form) return null;

    const button = form.querySelector('button[type="submit"]');
    if (!button) return null;

    let submitted = false;

    form.addEventListener('submit', () => {
        if (submitted) return;

        submitted = true;
        button.disabled = true;
        button.dataset.idleLabel = button.dataset.idleLabel || button.textContent.trim();
        button.textContent = busyLabel;
        button.setAttribute('aria-busy', 'true');
    });

    return { button };
};

export { initSubmitLock };
