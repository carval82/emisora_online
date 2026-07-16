const CACHE_NAME = 'emisora-v10';
const urlsToCache = ['/manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Nunca cachear API ni archivos de audio
    if (url.pathname.startsWith('/api/') || url.pathname.includes('/storage/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        caches.match(event.request).then((response) => response || fetch(event.request))
    );
});
