/* ============================================================
   sw.js — SVMS Service Worker v2.0.0
   Strategies:
     navigation  → network-first  + offline.html fallback
     /assets/**  → cache-first
     /api/**     → network-first (5 s timeout) + cache + offline JSON
     /pages/notifications.php → stale-while-revalidate
     POST        → never cached; checkout queued via Background Sync
   ============================================================ */

const CACHE_NAME   = 'svms-v2.0.0';
const OFFLINE_URL  = '/offline.html';
const API_TIMEOUT  = 5000; // ms

// ── Pre-cache manifest ─────────────────────────────────────────────────────
const PRECACHE = [
  '/offline.html',
  '/manifest.json',
  // CSS
  '/assets/css/tokens.css',
  '/assets/css/base.css',
  '/assets/css/layout.css',
  '/assets/css/components.css',
  '/assets/css/forms.css',
  '/assets/css/themes.css',
  '/assets/css/fonts.css',
  // JS
  '/assets/js/theme.js',
  '/assets/js/main.js',
  '/assets/js/i18n.js',
  '/assets/js/validation.js',
  '/assets/js/notifications.js',
  // Images / icons
  '/assets/img/logo.svg',
  '/assets/img/default-avatar.svg',
  '/assets/img/empty-state.svg',
  '/assets/img/icons/icon-192.png',
  '/assets/img/icons/icon-512.png',
  '/assets/img/icons/apple-touch-icon.png',
];

// ── Helper: race fetch against a timeout ──────────────────────────────────
function fetchWithTimeout(request, ms) {
  return new Promise(function(resolve, reject) {
    var timer = setTimeout(function() {
      reject(new Error('timeout'));
    }, ms);
    fetch(request).then(function(res) {
      clearTimeout(timer);
      resolve(res);
    }, function(err) {
      clearTimeout(timer);
      reject(err);
    });
  });
}

// ── Install: precache shell + skip waiting only on first install ───────────
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      // addAll aborts on any failure; use individual puts so one missing
      // asset (e.g. icon not yet generated) doesn't block the whole SW.
      return Promise.allSettled(
        PRECACHE.map(function(url) {
          return cache.add(url).catch(function(err) {
            console.warn('[SW] precache miss:', url, err.message);
          });
        })
      );
    })
    // NOTE: do NOT call self.skipWaiting() here.
    // The update toast in main.js detects the "waiting" state and lets
    // the user choose when to apply the new version.
    // On brand-new installs (no controller) the page auto-reloads anyway.
  );
});

// ── Activate: delete stale caches, claim clients ─────────────────────────
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys
          .filter(function(key) { return key !== CACHE_NAME; })
          .map(function(key) { return caches.delete(key); })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// ── Message: SKIP_WAITING (triggered by update toast) ────────────────────
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// ── Fetch routing ─────────────────────────────────────────────────────────
self.addEventListener('fetch', function(event) {
  var req = event.request;
  var url = new URL(req.url);

  // Only handle same-origin GET (and the special POST sync path below)
  if (url.origin !== self.location.origin) return;

  // ── POST: never cache; queue checkout for background sync ──────────────
  if (req.method === 'POST') {
    if (url.pathname === '/api/checkout.php') {
      event.respondWith(handleOfflineCheckout(req));
    }
    // All other POSTs: pass through unmodified
    return;
  }

  if (req.method !== 'GET') return;

  // ── Notifications page: stale-while-revalidate ─────────────────────────
  if (url.pathname === '/pages/notifications.php') {
    event.respondWith(staleWhileRevalidate(req));
    return;
  }

  // ── API: network-first with timeout, cache fallback, offline JSON ──────
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(apiStrategy(req));
    return;
  }

  // ── Static assets: cache-first ─────────────────────────────────────────
  if (/\.(css|js|svg|png|jpg|jpeg|webp|ico|woff2?|ttf|otf)$/i.test(url.pathname)) {
    event.respondWith(cacheFirst(req));
    return;
  }

  // ── Navigation (HTML pages): network-first + offline fallback ──────────
  if (req.mode === 'navigate') {
    event.respondWith(navigationStrategy(req));
    return;
  }
});

// ── Strategy implementations ──────────────────────────────────────────────

function navigationStrategy(req) {
  return fetch(req)
    .then(function(res) {
      if (res.ok) {
        var clone = res.clone();
        caches.open(CACHE_NAME).then(function(c) { c.put(req, clone); });
      }
      return res;
    })
    .catch(function() {
      return caches.match(req).then(function(cached) {
        return cached || caches.match(OFFLINE_URL);
      });
    });
}

function cacheFirst(req) {
  return caches.match(req).then(function(cached) {
    if (cached) return cached;
    return fetch(req).then(function(res) {
      if (res.ok) {
        var clone = res.clone();
        caches.open(CACHE_NAME).then(function(c) { c.put(req, clone); });
      }
      return res;
    });
  });
}

function apiStrategy(req) {
  return fetchWithTimeout(req.clone(), API_TIMEOUT)
    .then(function(res) {
      if (res.ok) {
        var clone = res.clone();
        caches.open(CACHE_NAME).then(function(c) { c.put(req, clone); });
      }
      return res;
    })
    .catch(function() {
      return caches.match(req).then(function(cached) {
        if (cached) return cached;
        return new Response(
          JSON.stringify({ ok: false, offline: true }),
          { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
      });
    });
}

function staleWhileRevalidate(req) {
  return caches.open(CACHE_NAME).then(function(cache) {
    return cache.match(req).then(function(cached) {
      var fetchPromise = fetch(req).then(function(res) {
        if (res.ok) cache.put(req, res.clone());
        return res;
      });
      return cached || fetchPromise;
    });
  });
}

// ── Offline checkout: store & register background sync ───────────────────
var DB_NAME    = 'svms-sync';
var DB_VERSION = 1;
var STORE_NAME = 'pending-checkouts';

function openDB() {
  return new Promise(function(resolve, reject) {
    var req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = function(e) {
      e.target.result.createObjectStore(STORE_NAME, {
        keyPath: 'id', autoIncrement: true
      });
    };
    req.onsuccess = function(e) { resolve(e.target.result); };
    req.onerror   = function(e) { reject(e.target.error); };
  });
}

function handleOfflineCheckout(req) {
  return req.clone().text().then(function(body) {
    return openDB().then(function(db) {
      return new Promise(function(resolve, reject) {
        var tx    = db.transaction(STORE_NAME, 'readwrite');
        var store = tx.objectStore(STORE_NAME);
        store.add({
          url:     req.url,
          method:  req.method,
          headers: Array.from(req.headers.entries()),
          body:    body,
          ts:      Date.now(),
        });
        tx.oncomplete = resolve;
        tx.onerror    = reject;
      });
    }).then(function() {
      // Register background sync if supported
      if (self.registration && 'sync' in self.registration) {
        return self.registration.sync.register('checkout-sync');
      }
    }).then(function() {
      return new Response(
        JSON.stringify({ ok: false, offline: true, queued: true }),
        { status: 202, headers: { 'Content-Type': 'application/json' } }
      );
    });
  }).catch(function() {
    return new Response(
      JSON.stringify({ ok: false, offline: true }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  });
}

// ── Background Sync: replay queued checkouts ─────────────────────────────
self.addEventListener('sync', function(event) {
  if (event.tag === 'checkout-sync') {
    event.waitUntil(replayCheckouts());
  }
});

function replayCheckouts() {
  return openDB().then(function(db) {
    return new Promise(function(resolve, reject) {
      var tx      = db.transaction(STORE_NAME, 'readwrite');
      var store   = tx.objectStore(STORE_NAME);
      var getAllReq = store.getAll();
      getAllReq.onsuccess = function() {
        var items = getAllReq.result;
        if (!items.length) { resolve(); return; }

        var promises = items.map(function(item) {
          var headers = {};
          (item.headers || []).forEach(function(pair) {
            headers[pair[0]] = pair[1];
          });
          return fetch(item.url, {
            method:  item.method,
            headers: headers,
            body:    item.body,
          }).then(function(res) {
            if (res.ok) {
              // Remove successfully replayed item
              db.transaction(STORE_NAME, 'readwrite')
                .objectStore(STORE_NAME)
                .delete(item.id);
            }
          }).catch(function() {
            // Will retry on next sync
          });
        });
        Promise.all(promises).then(resolve, reject);
      };
      getAllReq.onerror = reject;
    });
  });
}

/*
 * ── PUSH NOTIFICATIONS (stub — TODO) ──────────────────────────────────────
 *
 * Push notifications require:
 *   1. A push server (e.g. web-push npm library on a Node backend).
 *   2. VAPID keys generated once: npx web-push generate-vapid-keys
 *   3. Client subscription: registration.pushManager.subscribe(...)
 *   4. The server POSTing encrypted payloads to the push service.
 *
 * To enable:
 *   self.addEventListener('push', function(event) {
 *     var data = event.data ? event.data.json() : {};
 *     event.waitUntil(
 *       self.registration.showNotification(data.title || 'SVMS', {
 *         body:    data.body || '',
 *         icon:    '/assets/img/icons/icon-192.png',
 *         badge:   '/assets/img/icons/icon-192.png',
 *         data:    { url: data.url || '/pages/notifications.php' },
 *       })
 *     );
 *   });
 *
 *   self.addEventListener('notificationclick', function(event) {
 *     event.notification.close();
 *     event.waitUntil(
 *       clients.openWindow(event.notification.data.url)
 *     );
 *   });
 */


const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/offline.html',
  '/assets/css/tokens.css',
  '/assets/css/base.css',
  '/assets/css/layout.css',
  '/assets/css/components.css',
  '/assets/css/forms.css',
  '/assets/css/dashboard.css',
  '/assets/css/kiosk.css',
  '/assets/css/themes.css',
  '/assets/js/theme.js',
  '/assets/js/main.js',
  '/assets/js/validation.js',
  '/assets/js/dashboard.js',
  '/assets/js/kiosk.js',
  '/assets/js/webcam.js',
  '/assets/js/notifications.js',
  '/assets/js/i18n.js',
  '/assets/img/logo.svg',
  '/assets/img/default-avatar.svg',
  '/assets/img/empty-state.svg',
];

// Install: pre-cache static assets
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(STATIC_ASSETS);
    }).then(function() {
      return self.skipWaiting();
    })
  );
});

// Activate: clean old caches
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys.filter(function(key) { return key !== CACHE_NAME; })
            .map(function(key) { return caches.delete(key); })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// Fetch strategy:
// - API endpoints: network-first, no cache
// - Static assets: cache-first
// - Pages: network-first, fallback to offline.html
self.addEventListener('fetch', function(event) {
  var url = new URL(event.request.url);

  // Skip non-GET and cross-origin
  if (event.request.method !== 'GET' || url.origin !== location.origin) return;

  // API: network-only (never cache)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request).catch(function() {
        return new Response(JSON.stringify({error: 'Offline'}), {
          headers: {'Content-Type': 'application/json'},
        });
      })
    );
    return;
  }

  // Static assets: cache-first
  var isStatic = /\.(css|js|svg|png|jpg|jpeg|ico|woff2?)$/i.test(url.pathname);
  if (isStatic) {
    event.respondWith(
      caches.match(event.request).then(function(cached) {
        return cached || fetch(event.request).then(function(response) {
          if (response.ok) {
            var clone = response.clone();
            caches.open(CACHE_NAME).then(function(cache) { cache.put(event.request, clone); });
          }
          return response;
        });
      })
    );
    return;
  }

  // Pages: network-first, fallback to offline page
  event.respondWith(
    fetch(event.request).catch(function() {
      return caches.match(event.request).then(function(cached) {
        return cached || caches.match(OFFLINE_URL);
      });
    })
  );
});
