// Polls the progress of running tasks every 3 seconds and reloads the page when they finish.
export function initTaskProgress(): void {
    const panels = document.querySelectorAll<HTMLElement>('[data-task-poll]');
    if (panels.length === 0) {
        return;
    }

    const poll = async (): Promise<void> => {
        let active = false;
        for (const panel of panels) {
            const response = await fetch(panel.dataset.taskPoll ?? '', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                continue;
            }
            const data = (await response.json()) as { active: boolean; label: string; percent?: number };
            const label = panel.querySelector<HTMLElement>('[data-task-label]');
            const bar = panel.querySelector<HTMLElement>('[data-task-bar]');
            if (label) {
                label.textContent = data.label;
            }
            if (bar && data.percent !== undefined) {
                bar.style.width = `${data.percent}%`;
            }
            active = active || data.active;
        }
        if (active) {
            window.setTimeout(() => void poll(), 3000);
        } else {
            window.location.reload();
        }
    };

    window.setTimeout(() => void poll(), 3000);
}
