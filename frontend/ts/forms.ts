import netteForms from 'nette-forms';

// Disables a submit button after the first click so a slow request is not sent twice, and re-evaluates
// the conditional parts of a form while typing: nette-forms toggles them only on "change", which a text
// field fires after it loses focus (filling in a capacity should show the waiting list option at once).
export function initForms(): void {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.allowResubmit !== undefined) {
            return;
        }
        for (const button of form.querySelectorAll<HTMLButtonElement>('button[type="submit"]')) {
            window.setTimeout(() => {
                button.disabled = true;
            }, 0);
        }
    });

    document.addEventListener('input', (event) => {
        const field = event.target;
        if (field instanceof HTMLInputElement && field.form !== null && field.dataset.netteRules !== undefined) {
            netteForms.toggleForm(field.form, event);
        }
    });
}
