/*
 * Nexoma Service Worker.
 *
 * Bewusst minimal: Es werden nur unveraenderliche, oeffentliche Dateien (Build-Assets, Icons) zwischengespeichert.
 * HTML-Seiten, Inertia-Antworten, OAuth, MCP und alle nicht-GET-Anfragen gehen immer direkt ans Netz und werden
 * nie gespeichert (angemeldete Inhalte und Stellendaten bleiben nicht auf dem Geraet liegen).
 * Ohne Verbindung zeigt eine Navigation die statische Seite /offline.html.
 */
const VERSION = 'nexoma-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/favicon-32.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== VERSION)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')
    );
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );

        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(VERSION)
                                .then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );
    }
});
