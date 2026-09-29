// Disables a submit button after the first click so a slow request is not sent twice.
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
}
