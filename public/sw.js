const CACHE_NAME = 'gafco-driver-shell-v1';
const STATIC_ASSETS = [
    '/offline',
    '/manifest.webmanifest',
    '/icons/gafco-driver-192.png',
    '/icons/gafco-driver-512.png',
    '/icons/gafco-driver-maskable-512.png',
];

const cacheDriverShell = async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(STATIC_ASSETS);

    try {
        const response = await fetch('/build/manifest.json', { cache: 'no-store' });
        if (!response.ok) return;

        const manifest = await response.clone().json();
        const entry = manifest['resources/js/app.js'];
        const buildAssets = [entry?.file, ...(entry?.css || [])]
            .filter(Boolean)
            .map((path) => path.startsWith('/') ? path : `/${path}`);

        await cache.put('/build/manifest.json', response);
        if (buildAssets.length) await cache.addAll(buildAssets);
    } catch {
        // The safe offline page remains available even if build assets cannot be precached.
    }
};

self.addEventListener('install', (event) => {
    event.waitUntil(cacheDriverShell());
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));
        return;
    }

    if (['style', 'script', 'font', 'image'].includes(request.destination)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
});

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data?.json() ?? {};
    } catch {
        payload = { body: event.data?.text() ?? 'Ai o notificare nouă.' };
    }

    const title = payload.title || 'GAFCO Șofer';
    const options = {
        body: payload.body || payload.message || 'Ai o actualizare nouă.',
        icon: payload.icon || '/icons/gafco-driver-192.png',
        badge: payload.badge || '/icons/gafco-driver-192.png',
        lang: payload.lang || 'ro',
        tag: payload.tag || undefined,
        vibrate: payload.vibrate || [180, 80, 180],
        data: payload.data || { url: '/notificari' },
        actions: payload.actions || [],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const requestedTarget = new URL(event.notification.data?.url || '/notificari', self.location.origin);
    const target = requestedTarget.origin === self.location.origin
        ? requestedTarget.href
        : new URL('/notificari', self.location.origin).href;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        const existing = clients.find((client) => client.url === target);
        if (existing) return existing.focus();
        return self.clients.openWindow(target);
    }));
});
