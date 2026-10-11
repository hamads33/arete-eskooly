'use strict';

const VERSION = '20261012-offline-retry-15';
const STATIC_CACHE = `arete-static-${VERSION}`;
const RUNTIME_CACHE = `arete-runtime-${VERSION}`;
const APPROVED_CACHES = new Set([STATIC_CACHE, RUNTIME_CACHE]);
const PRECACHE = [
  '/offline.html',
  '/assets/css/variables.css?v=2',
  '/assets/css/reset.css',
  '/assets/css/base.css',
  '/assets/css/pwa.css',
  '/assets/css/install.css',
  '/assets/css/install-refinement.css',
  '/assets/css/mobile-ui.css?v=19',
  '/assets/js/mobile-ux.js?v=8',
  '/assets/css/sms-center.css?v=3',
  '/assets/js/sms-center.js?v=2',
  '/assets/css/fee-workspace.css?v=101',
  '/assets/js/fee-workspace.js?v=1',
  '/assets/js/pwa.js',
  '/assets/images/arete-logo.png',
  '/assets/pwa/arete-icon-192-v2.png',
  '/assets/pwa/arete-icon-512-v2.png',
  '/assets/pwa/arete-maskable-512-v2.png'
];
const STATIC_EXTENSION = /\.(?:css|js|png|jpe?g|webp|svg|woff2?)$/i;

self.addEventListener('install', (event) => {
  // Take over at once: pages are always fetched from the network, so an update needs no hand-over.
  event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.filter((name) => name.startsWith('arete-') && !APPROVED_CACHES.has(name)).map((name) => caches.delete(name))))
      .then(() => self.registration.navigationPreload ? self.registration.navigationPreload.enable() : null)
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Every HTML navigation is network-only. Private pages never enter Cache Storage.
  if (request.mode === 'navigate') {
    event.respondWith(navigate(event));
    return;
  }

  // Only explicitly safe files under /assets/ are runtime cached. Uploaded/private files are not.
  if (url.pathname.startsWith('/assets/') && STATIC_EXTENSION.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request));
  }
});

// A browser without a service worker silently retries a page load after a brief network blip
// (Wi-Fi/4G switch, reset connection). fetch() inside a service worker does not, so retry here
// before showing the offline page; only a connection that stays down for ~2 seconds is "offline".
const RETRY_DELAYS = [500, 1500];
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function navigate(event) {
  try {
    const preloaded = await event.preloadResponse;
    if (preloaded) return preloaded;
  } catch (error) { /* fall through to a normal fetch */ }
  for (let attempt = 0; ; attempt++) {
    try {
      return await fetch(event.request);
    } catch (error) {
      if (attempt >= RETRY_DELAYS.length) break;
      await wait(RETRY_DELAYS[attempt]);
    }
  }
  return (await caches.match('/offline.html')) || Response.error();
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await cache.match(request);
  const refresh = fetch(request).then((response) => {
    if (response.ok && response.type === 'basic') cache.put(request, response.clone());
    return response;
  }).catch(() => null);
  return cached || refresh || Response.error();
}
