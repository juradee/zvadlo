/// <reference lib="webworker" />
// Service worker of the leaders' PWA (spec ch. 15). Pages are network first: personal data are never served
// from a cache while online. Cached are only the offline page, assets with a digest and the last shown list of
// registrations of every event, which the leader can open at the meeting point without signal. Logging out
// deletes the lists. The release hash names the caches, so a new release starts clean.
declare const self: ServiceWorkerGlobalScope;

const VERSION = '__ZVADLO_RELEASE__';
const SHELL_CACHE = `zvadlo-shell-${VERSION}`;
const ASSET_CACHE = `zvadlo-assets-${VERSION}`;
const REGISTRATIONS_CACHE = `zvadlo-registrations-${VERSION}`;
const OFFLINE_URL = '/offline';
const REGISTRATION_LIST = /^\/akce\/\d+\/prihlasky\/?$/;
const LOGOUT = /^\/(logout|sign\/(out|skautis-logout))(\/|$)/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.add(OFFLINE_URL))
            .catch(() => undefined)
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    const keep = [SHELL_CACHE, ASSET_CACHE, REGISTRATIONS_CACHE];
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(names.filter((name) => name.startsWith('zvadlo-') && !keep.includes(name)).map((name) => caches.delete(name))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(cacheFirst(request));
        return;
    }
    if (request.mode !== 'navigate') {
        return;
    }
    if (LOGOUT.test(url.pathname)) {
        event.respondWith(caches.delete(REGISTRATIONS_CACHE).then(() => fetch(request)));
        return;
    }
    event.respondWith(navigate(event, request, url));
});

async function cacheFirst(request: Request): Promise<Response> {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }
    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(ASSET_CACHE);
        await cache.put(request, response.clone());
    }
    return response;
}

async function navigate(event: FetchEvent, request: Request, url: URL): Promise<Response> {
    const isList = REGISTRATION_LIST.test(url.pathname);
    const key = new Request(url.origin + url.pathname);

    if (isList && url.searchParams.get('offline') === '1') {
        const saved = await snapshot(key);
        if (saved) {
            return saved;
        }
    }

    try {
        const response = await fetch(request);
        if (isList && response.ok && response.type === 'basic' && !response.redirected && (response.headers.get('Content-Type') ?? '').includes('text/html')) {
            event.waitUntil(storeSnapshot(key, response.clone()));
        }
        return response;
    } catch {
        return (await snapshot(key))
            ?? (await caches.match(OFFLINE_URL))
            ?? new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

async function storeSnapshot(key: Request, response: Response): Promise<void> {
    const html = await response.text();
    const title = /<title>([^<]*)<\/title>/i.exec(html)?.[1] ?? '';
    const cache = await caches.open(REGISTRATIONS_CACHE);
    await cache.put(key, new Response(html, {
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Zvadlo-Cached-At': new Date().toISOString(),
            'X-Zvadlo-Title': encodeURIComponent(title),
        },
    }));
}

// The saved copy is marked on <body>, so the page shows when it was taken and that nothing can be changed.
async function snapshot(key: Request): Promise<Response | undefined> {
    const cache = await caches.open(REGISTRATIONS_CACHE);
    const cached = await cache.match(key);
    if (!cached) {
        return undefined;
    }
    const cachedAt = cached.headers.get('X-Zvadlo-Cached-At') ?? '';
    const html = (await cached.text()).replace('<body', `<body data-offline-cached-at="${cachedAt}"`);
    return new Response(html, {
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Zvadlo-Cached-At': cachedAt },
    });
}

// A module, so that the typed `self` above shadows the WorkerGlobalScope declaration of the lib.
export {};
