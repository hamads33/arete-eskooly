# Arete Progressive Web App

## Architecture and installation

Arete is one root-scoped PWA for administrators, teachers/staff, students and parents. PHP sessions,
RBAC and existing redirects select the correct workspace after launch. `manifest.webmanifest` uses
`/` for its id, start URL and scope, standalone display, the maroon `#7A1632` theme and branded
192/512/maskable icons. Shared layout partials expose the manifest, Apple metadata and centralized
`pwa.js` registration on auth, admin, portal and error pages.

Supported browsers show **Install App** whenever the app is not already running standalone. It opens
the native prompt when `beforeinstallprompt` is available and otherwise explains the browser-menu
installation path. iOS uses the Apple touch icon and Add to Home Screen browser workflow.

## Service worker and private-data protection

`public/sw.js` is root scoped. It precaches only `offline.html`, PWA/core styles and script, the
public logo, and app icons. Same-origin static files below `/assets/` use stale-while-revalidate.
All navigations are network-only and fall back to the neutral offline page only when fetch fails.
Every non-GET request and every third-party request bypasses the worker.

The worker never caches authenticated HTML, `/uploads/`, business JSON, forms, PDFs, reports,
receipts, salary/fee data, attendance, marks/results, messages, credentials, Google OAuth responses,
Live Class pages or Meet links. PHP responses retain `Cache-Control: no-store`. Logout remains
server-authoritative and safe static caches are not unnecessarily cleared.

## Offline and updates

Offline mode displays a restrained global status and prevents form submission/file selection as a
UX guard; the server remains authoritative. Live Classes, PDFs and all writes require the network.
There is no fake offline business mode, mutation queue, Background Sync or automatic replay.

On installed mobile PWAs, student Live Class join actions use the current app context for the
authorised LMS join endpoint. This lets its validated `meet.google.com` redirect reach the mobile
operating system's Google Meet app-link handler; ordinary browser and desktop links retain their
new-tab behaviour.

New workers install quietly and remain waiting while existing app tabs are open. The browser
activates them through its normal lifecycle after those tabs close, so there is no update prompt
or forced reload of an unsaved form. Activation removes obsolete Arete caches.

## Local development

Set `PWA_ENABLED=false` in local `.env` to disable registration. To inspect manually, use browser
developer tools under Application: Manifest, Service Workers and Cache Storage. Use the
service-worker panel to check for a build, or Unregister plus Clear site data to reset. A
service worker is allowed on HTTPS and the standard `localhost` development exception only.

## Production deployment

- Serve the app over HTTPS with `APP_URL` set to the canonical HTTPS origin.
- Serve `.webmanifest` as `application/manifest+json`.
- Serve `/sw.js` from the origin root with `Cache-Control: no-cache` and
  `Service-Worker-Allowed: /`; do not apply long immutable caching to it.
- Static versioned assets may receive long-lived cache headers. Dynamic PHP/private responses must
  retain `no-store` and sensitive downloads must remain online-only.
- Verify icon/offline paths and reverse-proxy scheme forwarding, then bump the worker `VERSION` for
  each cache-affecting release.

## Manual QA checklist

1. Open the app over HTTPS or localhost; confirm manifest and worker discovery.
2. Confirm Install App appears only when supported and installation opens standalone.
3. Verify valid and expired sessions and Admin, Teacher, Student and Parent workspaces.
4. Go offline and navigate to a protected page; confirm the premium offline page, never stale HTML.
5. Confirm safe CSS/icons load while offline and forms/uploads/Live Classes remain unavailable.
6. Return online and confirm normal navigation and the Connection restored status.
7. Deploy a bumped worker and confirm no update prompt or forced reload interrupts an edited form.
8. Close and reopen all app tabs, then verify logout and responsive layouts at desktop, tablet
   and 360-430px mobile widths including standalone safe areas.

Push notifications are a separate future phase. This implementation does not request notification
permission or introduce Firebase.
