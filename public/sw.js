'use strict';

const VERSION = '20261006-mobile-ux-5';
const STATIC_CACHE = `arete-static-${VERSION}`;
const RUNTIME_CACHE = `arete-runtime-${VERSION}`;
const APPROVED_CACHES = new Set([STATIC_CACHE, RUNTIME_CACHE]);
const PRECACHE = [
  '/offline.html',
  '/assets/css/variables.css',
  '/assets/css/reset.css',
  '/assets/css/base.css',
  '/assets/css/pwa.css',
  '/assets/css/install.css',
  '/assets/css/install-refinement.css',
  '/assets/css/mobile-ui.css',
  '/assets/js/mobile-ux.js?v=3',
  '/assets/css/fee-workspace.css?v=1',
  '/assets/js/fee-workspace.js?v=1',
  '/assets/js/pwa.js',
  '/assets/images/arete-logo.png',
  '/assets/pwa/arete-icon-192-v2.png',
  '/assets/pwa/arete-icon-512-v2.png',
  '/assets/pwa/arete-maskable-512-v2.png'
];
const STATIC_EXTENSION = /\.(?:css|js|png|jpe?g|webp|svg|woff2?)$/i;

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.filter((name) => name.startsWith('arete-') && !APPROVED_CACHES.has(name)).map((name) => caches.delete(name))))
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
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    return;
  }

  // Only explicitly safe files under /assets/ are runtime cached. Uploaded/private files are not.
  if (url.pathname.startsWith('/assets/') && STATIC_EXTENSION.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request));
  }
});

async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await cache.match(request);
  const refresh = fetch(request).then((response) => {
    if (response.ok && response.type === 'basic') cache.put(request, response.clone());
    return response;
  }).catch(() => null);
  return cached || refresh || Response.error();
}
