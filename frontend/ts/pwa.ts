// The leaders' PWA: registers the service worker, offers the install prompt, marks a saved copy of a page and
// lists the saved registration lists on the offline page. Public pages never load this (no data-pwa).
interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
}

const DISMISSED_KEY = 'zvadlo.pwa.installDismissed';

export function initPwa(): void {
    if (document.documentElement.dataset.pwa !== 'leader') {
        return;
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    }
    showOfflineBanner();
    initInstallPrompt();
    void renderOfflineList();
}

export function formatCzechDateTime(iso: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return iso;
    }
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function showOfflineBanner(): void {
    const cachedAt = document.body.dataset.offlineCachedAt;
    const banner = document.querySelector<HTMLElement>('[data-offline-banner]');
    if (!cachedAt || !banner) {
        return;
    }
    const time = banner.querySelector<HTMLElement>('[data-offline-cached-at]');
    if (time) {
        time.textContent = formatCzechDateTime(cachedAt);
    }
    banner.querySelector<HTMLAnchorElement>('[data-offline-reload]')?.addEventListener('click', (event) => {
        event.preventDefault();
        window.location.href = window.location.pathname;
    });
    banner.hidden = false;
}

function initInstallPrompt(): void {
    const bar = document.querySelector<HTMLElement>('[data-pwa-install]');
    if (!bar || window.matchMedia('(display-mode: standalone)').matches) {
        return;
    }
    let dismissed = false;
    try {
        dismissed = window.localStorage.getItem(DISMISSED_KEY) !== null;
    } catch {
        dismissed = false;
    }
    if (dismissed) {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        const prompt = event as BeforeInstallPromptEvent;
        bar.hidden = false;
        bar.querySelector<HTMLButtonElement>('[data-pwa-install-button]')?.addEventListener('click', () => {
            bar.hidden = true;
            void prompt.prompt();
        });
    });
    bar.querySelector<HTMLButtonElement>('[data-pwa-install-dismiss]')?.addEventListener('click', () => {
        bar.hidden = true;
        try {
            window.localStorage.setItem(DISMISSED_KEY, new Date().toISOString());
        } catch {
            // Private mode: the bar simply comes back next time.
        }
    });
}

async function renderOfflineList(): Promise<void> {
    const list = document.querySelector<HTMLElement>('[data-offline-list]');
    if (!list || !('caches' in window)) {
        return;
    }
    const empty = document.querySelector<HTMLElement>('[data-offline-empty]');
    const items: { url: string; title: string; cachedAt: string }[] = [];
    for (const name of await caches.keys()) {
        if (!name.startsWith('zvadlo-registrations-')) {
            continue;
        }
        const cache = await caches.open(name);
        for (const request of await cache.keys()) {
            const response = await cache.match(request);
            if (!response) {
                continue;
            }
            items.push({
                url: new URL(request.url).pathname,
                title: decodeURIComponent(response.headers.get('X-Zvadlo-Title') ?? '') || request.url,
                cachedAt: response.headers.get('X-Zvadlo-Cached-At') ?? '',
            });
        }
    }

    items.sort((a, b) => b.cachedAt.localeCompare(a.cachedAt));
    for (const item of items) {
        const li = document.createElement('li');
        li.className = 'list-group-item';
        const link = document.createElement('a');
        link.href = `${item.url}?offline=1`;
        link.dataset.test = 'offline-cached-page';
        link.textContent = item.title;
        const when = document.createElement('div');
        when.className = 'small text-body-secondary';
        when.textContent = item.cachedAt === '' ? '' : `uloženo ${formatCzechDateTime(item.cachedAt)}`;
        li.append(link, when);
        list.append(li);
    }
    if (empty) {
        empty.hidden = items.length > 0;
    }
}
