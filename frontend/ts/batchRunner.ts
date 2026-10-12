// Runs a server action in batches over AJAX until nothing remains, showing progress, then reloads.
// A container [data-batch-run] starts on load (contacts from skautIS); a button [data-batch-start] starts
// on click and shows the sibling [data-batch-panel] (writing participants into skautIS).
// A response may carry `next`: the offset of the next batch, so rows that failed are not retried in a loop.
export function initBatchRunner(): void {
    const run = async (url: string, total: number, panel: HTMLElement | null, next = 0, previous = Number.POSITIVE_INFINITY): Promise<void> => {
        const label = panel?.querySelector<HTMLElement>('[data-progress-label]') ?? null;
        const bar = panel?.querySelector<HTMLElement>('[data-progress-bar]') ?? null;
        const target = new URL(url, window.location.href);
        if (next > 0) {
            target.searchParams.set('offset', String(next));
        }
        const response = await fetch(target, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) {
            if (label) {
                label.textContent = 'Požadavek se nezdařil. Obnovte stránku a zkuste to znovu.';
            }
            return;
        }
        const data = (await response.json()) as { remaining: number; next?: number; error?: string };
        if (data.error) {
            if (label) {
                label.textContent = data.error;
            }
            window.setTimeout(() => window.location.reload(), 2500);
            return;
        }
        if (label) {
            label.textContent = data.remaining > 0 ? `Zbývá ${data.remaining}` : 'Hotovo.';
        }
        if (bar) {
            bar.style.width = `${Math.round(((total - data.remaining) / total) * 100)}%`;
        }
        // The server always makes progress; the guard only protects against an endpoint that does not.
        if (data.remaining > 0 && data.remaining < previous) {
            await run(url, total, panel, data.next ?? 0, data.remaining);
        } else {
            window.location.reload();
        }
    };

    for (const box of document.querySelectorAll<HTMLElement>('[data-batch-run]')) {
        void run(box.dataset.batchRun ?? '', Math.max(1, Number(box.dataset.batchTotal ?? '1')), box);
    }

    document.addEventListener('click', (event) => {
        const button = event.target;
        if (!(button instanceof HTMLElement) || button.dataset.batchStart === undefined) {
            return;
        }
        event.preventDefault();
        const panel = button.closest('.card')?.querySelector<HTMLElement>('[data-batch-panel]') ?? null;
        panel?.classList.remove('d-none');
        button.setAttribute('disabled', 'disabled');
        void run(button.dataset.batchStart, Math.max(1, Number(button.dataset.batchTotal ?? '1')), panel);
    });
}
