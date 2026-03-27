const CACHE_VERSION = 'menu-cache-v1';
const WARM_CACHE = `${CACHE_VERSION}-warm`;

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
  const data = event && event.data;
  if (!data || data.type !== 'WARM_CACHE' || !Array.isArray(data.urls)) return;

  event.waitUntil((async () => {
    const cache = await caches.open(WARM_CACHE);
    const uniqueUrls = [...new Set(data.urls.filter((u) => typeof u === 'string' && u.trim()))];

    await Promise.all(uniqueUrls.map(async (url) => {
      try {
        const req = new Request(url, { cache: 'no-store' });
        const res = await fetch(req);
        // Cache successful and opaque responses; avoid blocking cross-origin warmups.
        if (res && (res.ok || res.type === 'opaque')) {
          await cache.put(req, res.clone());
        }
      } catch (_) {
        // Ignore warm-up failures; runtime fetch can still proceed network-first.
      }
    }));
  })());
});
