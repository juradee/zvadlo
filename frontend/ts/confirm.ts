// A submit button with data-confirm="text" asks before the form is sent.
export function initConfirmButtons(): void {
    document.addEventListener('click', (event) => {
        const button = event.target;
        if (!(button instanceof HTMLElement) || button.dataset.confirm === undefined) {
            return;
        }
        if (!window.confirm(button.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
}
