import { keepAliveSucceededEvent } from './sessionKeepAlive';

// Shows the minutes left of the skautIS login in the navbar; every successful keep-alive resets it.
export function initLogoutTimer(): void {
    const badge = document.querySelector<HTMLElement>('[data-logout-timer]');
    if (!badge) {
        return;
    }
    const sessionMinutes = Number.parseInt(badge.dataset.logoutTimer ?? '30', 10) || 30;
    let minutesLeft = sessionMinutes;
    let intervalId: number | null = null;

    const show = (): void => {
        if (minutesLeft < 0) {
            badge.textContent = 'odhlášeno';
            badge.title = 'Přihlášení do skautISu vypršelo.';
            if (intervalId !== null) {
                window.clearInterval(intervalId);
            }
            return;
        }
        badge.textContent = `${minutesLeft} min`;
        badge.title = `Do odhlášení zbývá ${minutesLeft} min`;
        badge.classList.toggle('text-bg-danger', minutesLeft <= 10);
        badge.classList.toggle('text-bg-light', minutesLeft > 10);
    };
    const reset = (): void => {
        minutesLeft = sessionMinutes;
        show();
        if (intervalId !== null) {
            window.clearInterval(intervalId);
        }
        intervalId = window.setInterval(() => {
            minutesLeft -= 1;
            show();
        }, 60_000);
    };

    reset();
    window.addEventListener(keepAliveSucceededEvent, reset);
    window.addEventListener('storage', (event) => {
        if (event.key === 'zvadlo.sessionKeepAlive.lastPingAt') {
            reset();
        }
    });
}
