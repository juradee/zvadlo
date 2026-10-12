// A checkbox with data-check-all="<selector>" toggles every enabled checkbox the selector matches, or every one
// inside the elements it matches.
export function initCheckAll(): void {
    document.addEventListener('change', (event) => {
        const master = event.target;
        if (!(master instanceof HTMLInputElement) || master.dataset.checkAll === undefined) {
            return;
        }
        for (const target of document.querySelectorAll(master.dataset.checkAll)) {
            const boxes = target instanceof HTMLInputElement ? [target] : target.querySelectorAll<HTMLInputElement>('input[type="checkbox"]');
            for (const box of boxes) {
                if (box.type === 'checkbox' && box.dataset.checkAll === undefined && !box.disabled) {
                    box.checked = master.checked;
                }
            }
        }
    });
}
