// Keeps the skautIS login alive while a tab is open: pings the server at the configured interval,
// coordinated across tabs through localStorage so several tabs do not ping at once (as h.skauting does).
const lastPingKey = 'zvadlo.sessionKeepAlive.lastPingAt';
const lockKey = 'zvadlo.sessionKeepAlive.lockedUntil';
export const keepAliveSucceededEvent = 'zvadlo:session-keep-alive-succeeded';
const minimumInterval = 60_000;
const tolerance = 60_000;
const lockTimeout = 30_000;

function anotherTabPingedRecently(now: number, interval: number): boolean {
    try {
        const lastPingAt = Number.parseInt(window.localStorage.getItem(lastPingKey) ?? '0', 10);
        if (Number.isFinite(lastPingAt) && lastPingAt > now - interval + tolerance) {
            return true;
        }
        const lockedUntil = Number.parseInt(window.localStorage.getItem(lockKey) ?? '0', 10);
        if (Number.isFinite(lockedUntil) && lockedUntil > now) {
            return true;
        }
        window.localStorage.setItem(lockKey, String(now + lockTimeout));
    } catch {
        return false;
    }
    return false;
}

function pingSucceeded(): void {
    try {
        window.localStorage.setItem(lastPingKey, String(Date.now()));
    } catch {
        // No localStorage: this tab still refreshed the session.
    }
    window.dispatchEvent(new CustomEvent(keepAliveSucceededEvent));
}

function releaseLock(): void {
    try {
        window.localStorage.removeItem(lockKey);
    } catch {
        // Nothing to release.
    }
}

export function initSessionKeepAlive(root: HTMLElement = document.body): void {
    const url = root.dataset.sessionKeepAliveUrl;
    const interval = Number.parseInt(root.dataset.sessionKeepAliveInterval ?? '', 10);
    if (!url || !Number.isFinite(interval) || interval < minimumInterval) {
        return;
    }

    let inProgress = false;
    const ping = (): void => {
        if (inProgress) {
            return;
        }
        const now = Date.now();
        if (anotherTabPingedRecently(now, interval)) {
            return;
        }
        inProgress = true;
        window
            .fetch(url, { method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: true, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => {
                if (response.ok) {
                    pingSucceeded();
                }
            })
            .catch(() => undefined)
            .finally(() => {
                releaseLock();
                inProgress = false;
            });
    };

    window.setInterval(ping, interval);
    window.addEventListener('pageshow', ping);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            ping();
        }
    });
}
