const CACHE_NAME = 'exam-generator-cache-v2';
const STATIC_ASSETS = [
    'offline.html'
];

self.addEventListener('install', (event) => {
  console.log('[ServiceWorker] Install');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(STATIC_ASSETS);
      })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  console.log('[ServiceWorker] Activate');
  event.waitUntil(
    caches.keys().then((cacheNames) =>
      Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[ServiceWorker] Deleting old cache:', cache);
            return caches.delete(cache);
          }
        })
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    event.respondWith(
        fetch(request)
            .then((response) => {
                return response;
            })
            .catch(() => {
                // عند انقطاع الاتصال
                // إذا كان الطلب لملف PHP أو API أظهر offline.html
                if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/')) {
                    return caches.match('offline.html');
                }

                // إذا كان الطلب لملف HTML
                if (request.headers.get('accept').includes('text/html')) {
                    return caches.match('offline.html');
                }

                // في باقي الحالات، حاول إرجاع الملف من الكاش إن وجد
                return caches.match(request);
            })
    );
});

