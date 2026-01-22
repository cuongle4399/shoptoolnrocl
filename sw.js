/* =================================
   SERVICE WORKER
   Cache static assets for offline use
   ================================= */

const CACHE_NAME = 'shoptoolnro-v1';
const STATIC_ASSETS = [
  '/ShopToolNro/',
  '/ShopToolNro/assets/css/style.css',
  '/ShopToolNro/assets/css/main.css',
  '/ShopToolNro/assets/css/header-mobile-fix.css',
  '/ShopToolNro/assets/css/mobile-performance.css',
  '/ShopToolNro/assets/js/api.js',
  '/ShopToolNro/assets/js/main.js',
  '/ShopToolNro/assets/js/mobile-performance.js'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
  console.log('Service Worker: Activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('Service Worker: Clearing old cache');
            return caches.delete(cache);
          }
        })
      );
    })
    .then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;
  
  // Skip API calls
  if (event.request.url.includes('/api/')) return;
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        return response || fetch(event.request).then(fetchResponse => {
          // Cache successful responses
          if (fetchResponse.ok) {
            return caches.open(CACHE_NAME).then(cache => {
              // Clone response to use it
              cache.put(event.request, fetchResponse.clone());
              return fetchResponse;
            });
          }
          return fetchResponse;
        });
      })
      .catch(() => {
        // Offline fallback
        if (event.request.destination === 'document') {
          return caches.match('/ShopToolNro/');
        }
      })
  );
});

// Background sync for offline requests
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-orders') {
    console.log('Service Worker: Syncing orders');
    // Handle background sync
  }
});

// Push notifications (optional)
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'ShopToolNro';
  const options = {
    body: data.body || 'Bạn có thông báo mới',
    icon: '/ShopToolNro/img/Logo.ico',
    badge: '/ShopToolNro/img/Logo.ico',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Notification click
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});
