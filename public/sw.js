/*
 * Sofor service worker: keeps the app usable on a weak or missing signal.
 *
 * - Built assets (hashed file names), icons and fonts: cache first.
 * - Trip pages and the dashboard: network first, the last copy when offline.
 * - Anything else that fails to load while offline: the offline page.
 *
 * Costs logged offline are not handled here - the page queues them in
 * IndexedDB and sends them itself (resources/js/offline.js).
 */
const VERSION = 'v1';
const ASSETS = `sofor-assets-${VERSION}`;
const PAGES = 'sofor-pages';
const OFFLINE_URL = '/offline';

// Pages worth keeping for offline use.
const KEEP = /^\/(trips\/\d+|dashboard)$/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(ASSETS)
            .then((cache) => cache.addAll([OFFLINE_URL, '/favicon.svg']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('sofor-assets-') && key !== ASSETS)
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Signing out clears the saved pages: they hold the trips' numbers.
self.addEventListener('message', (event) => {
    if (event.data === 'sofor:clear-pages') {
        event.waitUntil(caches.delete(PAGES));
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const sameOrigin = url.origin === self.location.origin;

    // Hashed build files, icons and Google Fonts never change under the same URL.
    if ((sameOrigin && /^\/(build|icons|images)\//.test(url.pathname)) || /fonts\.(googleapis|gstatic)\.com$/.test(url.hostname)) {
        event.respondWith(
            caches.open(ASSETS).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) return cached;

                const response = await fetch(request);
                if (response.ok || response.type === 'opaque') cache.put(request, response.clone());

                return response;
            }),
        );

        return;
    }

    if (!sameOrigin || request.mode !== 'navigate') {
        return;
    }

    event.respondWith((async () => {
        try {
            const response = await fetch(request);

            if (response.ok && KEEP.test(url.pathname) && !url.search) {
                const cache = await caches.open(PAGES);
                await cache.put(request, response.clone());
            }

            return response;
        } catch (error) {
            const saved = await caches.match(request, { ignoreSearch: true });

            return saved || (await caches.match(OFFLINE_URL)) || Response.error();
        }
    })());
});
