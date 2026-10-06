# PWA implementation plan

## Current architecture

The application is server-rendered Core PHP. `public/` is the document root; `public/index.php` is
the front controller and physical files bypass routing. Admin and portal pages share centralized
layouts; authentication and error pages have their own centralized layouts. Assets are local under
`public/assets`, and `asset()` adds a production file-mtime query string. Authentication remains
PHP-session based and middleware refreshes the database-backed identity on each protected request.
Dynamic responses already default to `Cache-Control: no-store, no-cache, must-revalidate`.

There is no existing manifest, service worker, install flow, offline page, or PWA icon set. The
real source logo is `public/assets/images/arete-logo.png`.

## Installability and role behavior

One root-scoped PWA serves Admin, Teacher/Staff, Student and Parent users. The manifest starts at
`/`, allowing normal session and role routing to choose the destination. Shared metadata and one
registration script are included through reusable partials in all browser layouts. Installation is
offered only after `beforeinstallprompt`, and never when already running standalone.

## Caching and offline strategy

- Precache only the neutral offline document, core public styles/scripts, logo and PWA icons.
- Use stale-while-revalidate only for same-origin files below `/assets/` with an approved static
  extension. Never runtime-cache `/uploads/` or arbitrary same-origin routes.
- Treat every navigation as network-only. On network failure return `offline.html`; never return
  cached dashboards, portal pages, reports, Meet URLs, OAuth pages or account screens.
- Ignore every non-GET request. No mutation queue, Background Sync, or replay is introduced.
- Remove obsolete Arete caches during activation. A waiting worker activates through the browser's
  normal lifecycle after existing app tabs close, without prompts or forced form reloads.

## Security risks and controls

The main risk is cross-user disclosure from cached authenticated HTML on shared devices. The worker
never stores navigation responses, JSON/business routes, uploads, PDFs, OAuth responses or Meet
links. Server-side `no-store` remains authoritative. Offline form blocking is UX only; CSRF, RBAC
and sessions remain unchanged.

## Files and testing

Create the manifest, root worker, offline page, branded icons, shared PWA partials, PWA JavaScript
and CSS, documentation and feature checks. Modify only central layouts, static-server guidance,
deployment/status documentation and the test runner. Validate manifest fields/icons, worker syntax
and allowlists, layout metadata, offline/install/update hooks, the full PHP suite and changed JS.

## Deployment

Serve the manifest as `application/manifest+json`, keep `sw.js` revalidatable with root scope,
retain dynamic private `no-store` headers, and serve over HTTPS (localhost is the development
exception). Push notifications are explicitly a future phase.
