// A button with data-copy="<selector>" copies the value of the referenced input to the clipboard.
// navigator.clipboard exists only in a secure context (https or localhost), so plain http and older
// browsers fall back to selecting the text and the legacy copy command.
export function initCopyButtons(): void {
    document.addEventListener('click', (event) => {
        const button = event.target;
        if (!(button instanceof HTMLButtonElement) || button.dataset.copy === undefined) {
            return;
        }
        const source = document.querySelector<HTMLInputElement>(button.dataset.copy);
        if (!source) {
            return;
        }
        const original = button.textContent;
        void copyValue(source).then((copied) => {
            button.textContent = copied ? 'Zkopírováno' : 'Zkopírujte ručně (Ctrl+C)';
            button.classList.toggle('btn-outline-danger', !copied);
            window.setTimeout(() => {
                button.textContent = original;
                button.classList.remove('btn-outline-danger');
            }, copied ? 2000 : 5000);
        });
    });
}

async function copyValue(source: HTMLInputElement): Promise<boolean> {
    if (window.isSecureContext && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(source.value);
            return true;
        } catch {
            // Permission denied or no user activation left: try the legacy way below.
        }
    }
    // The text stays selected, so the user can still press Ctrl+C when even this fails.
    source.focus();
    source.select();
    source.setSelectionRange(0, source.value.length);
    try {
        return document.execCommand('copy');
    } catch {
        return false;
    }
}
