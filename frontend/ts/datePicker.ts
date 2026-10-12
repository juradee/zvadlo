// Calendar button of the dd.mm.rrrr date fields (App\Presentation\Accessory\Form\DateInput): the text field keeps
// the Czech format, the browser's own picker (a phone's system calendar) opens on a hidden native date input.
export function initDatePickers(): void {
    document.addEventListener('click', (event) => {
        const button = (event.target as Element | null)?.closest<HTMLButtonElement>('[data-date-picker-open]');
        const group = button?.closest<HTMLElement>('[data-date-picker]');
        const text = group?.querySelector<HTMLInputElement>('input[type="text"]');
        const native = group?.querySelector<HTMLInputElement>('[data-date-picker-native]');
        if (!button || !text || !native) {
            return;
        }
        event.preventDefault();
        native.value = toIso(text.value);
        if (typeof native.showPicker === 'function') {
            try {
                native.showPicker();
                return;
            } catch {
                // Older browsers refuse showPicker() here; focusing the input still opens the picker in most.
            }
        }
        native.focus();
        native.click();
    });

    document.addEventListener('change', (event) => {
        const native = event.target;
        if (!(native instanceof HTMLInputElement) || native.dataset.datePickerNative === undefined || native.value === '') {
            return;
        }
        const text = native.closest('[data-date-picker]')?.querySelector<HTMLInputElement>('input[type="text"]');
        if (!text) {
            return;
        }
        const [year, month, day] = native.value.split('-');
        text.value = `${day}.${month}.${year}`;
        // Conditional parts of the form (e.g. the deadline notice) react to the new value.
        text.dispatchEvent(new Event('input', { bubbles: true }));
        text.dispatchEvent(new Event('change', { bubbles: true }));
        text.focus();
    });
}

function toIso(value: string): string {
    const match = /^\s*(\d{1,2})\s*[.,]\s*(\d{1,2})\s*[.,]\s*(\d{4})\s*$/.exec(value);
    if (!match) {
        return '';
    }
    const [, day, month, year] = match;

    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
}
