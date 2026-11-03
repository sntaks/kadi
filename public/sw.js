/*
 * Kadi service worker.
 *
 * Scope is "/" so this file must stay at the site root. Bump VERSION whenever
 * a precached asset (offline.html, icons) changes so clients pick up the new copy.
 *
 * Rules that must not be relaxed:
 *   - Never cache HTML, POSTs, Livewire updates, or anything holding a CSRF token
 *     or session state. Navigations are network-first and only fall back to the
 *     offline page when the network itself fails.
 *   - Every push MUST show a visible notification, or iOS revokes the subscription.
 */
const VERSION = 'v2';
const CACHE_PREFIX = 'kadi-static-';
const STATIC_CACHE = `${CACHE_PREFIX}${VERSION}`;
const OFFLINE_URL = '/offline.html';

// Fingerprint of everything clients keep precached: offline.html plus the PRECACHE line below.
// tests/Feature/Pwa/PwaAssetsTest.php recomputes it. If that test fails you changed something
// browsers already hold, so bump VERSION above (making every browser re-download it) and paste
// the new fingerprint here. See docs/pwa-push.md ("Service worker versioning").
// precache-fingerprint: 60574c239b03ac8c
const PRECACHE = [OFFLINE_URL, '/pwa-icons/icon-192.png'];

// Routes the worker must leave completely alone (OAuth redirects, auth, sessions, game launches,
// Livewire, uploads, and the Godot game assets).
const NEVER_HANDLE = new RegExp(
    '^/(livewire|broadcasting|sanctum|api|login|logout|register|forgot-password|reset-password|' +
    'two-factor-challenge|passkeys|email|user|consent|auth|push|storage|kadig|up|flux|_boost|play)(/|-|$)'
);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(async (cache) => {
            // Only the offline page (the first PRECACHE entry) is required. cache.addAll() would fail
            // the WHOLE install if any single file 404s (a misrouted icon once disabled push in
            // production), so everything else is best effort and the worker installs regardless.
            const [required, ...optional] = PRECACHE;

            await cache.add(required);
            await Promise.allSettled(optional.map((url) => cache.add(url)));
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith(CACHE_PREFIX) && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) return;
    if (NEVER_HANDLE.test(url.pathname)) return;
    if (request.headers.has('Authorization')) return;

    // Navigations: network-first. Never cache the HTML; only show the offline page
    // when the request itself fails (a 4xx/5xx response is passed through untouched).
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Vite build output is content-hashed, so cache-first is safe.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Icons keep stable names, so serve from cache but refresh in the background.
    if (url.pathname.startsWith('/pwa-icons/')) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }

    // Everything else: no respondWith, pure network passthrough.
});

async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const hit = await cache.match(request);
    if (hit) return hit;

    const response = await fetch(request);
    if (isCacheable(response)) cache.put(request, response.clone());
    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const hit = await cache.match(request);

    const refresh = fetch(request)
        .then((response) => {
            if (isCacheable(response)) cache.put(request, response.clone());
            return response;
        })
        .catch(() => hit);

    return hit || refresh;
}

function isCacheable(response) {
    return response && response.ok && response.type === 'basic';
}

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Kadi';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/pwa-icons/icon-192.png',
        badge: payload.badge || '/pwa-icons/badge-72.png',
        image: payload.image,
        tag: payload.tag,
        data: payload.data || {},
        actions: payload.actions || [],
        requireInteraction: !!payload.requireInteraction,
    };

    // MUST always show a notification (iOS revokes the subscription otherwise).
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    // An action button may map to its own URL via data.actions = { actionName: '/path' }.
    const requested = (event.action && data.actions && data.actions[event.action]) || data.url || '/';

    let target = new URL('/', self.location.origin);
    try {
        const candidate = new URL(requested, self.location.origin);
        if (candidate.origin === self.location.origin) target = candidate; // reject cross-origin
    } catch (e) {
        // fall through to the site root
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (client.url === target.href && 'focus' in client) return client.focus();
            }
            return self.clients.openWindow(target.href);
        })
    );
});

self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        try {
            const options = event.oldSubscription && event.oldSubscription.options;
            if (!event.newSubscription && options) {
                await self.registration.pushManager.subscribe(options);
            }
        } catch (e) {
            // Best effort only. The page re-syncs on its next load.
        }

        // The worker has no CSRF token, so the page does the server sync.
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        windows.forEach((client) => client.postMessage({ type: 'pushsubscriptionchange' }));
    })());
});
