# Production deployment

## Requirements

- PHP 8.2+ with PDO MySQL, OpenSSL, fileinfo and mbstring
- MySQL 8+ using `utf8mb4`
- A web root pointed at `public/`; application and `.env` files must not be web-accessible
- TLS termination at the web server or trusted proxy

## Environment

Copy `.env.example` to `.env` outside source control. Set `APP_ENV=production`, `APP_DEBUG=false`, the canonical HTTPS `APP_URL`, a unique `SESSION_NAME`, `SESSION_SECURE=true`, and production-only database credentials. Set `APP_KEY` to at least 32 cryptographically random bytes and never reuse or rotate it without a secret re-encryption plan. Restrict `.env` to the application service account.

Use a least-privilege MySQL account scoped to one database. It needs normal DML and migration DDL rights during a release; remove DDL rights from the runtime account where separate migration credentials are available.

## Release procedure

1. Put the application in a maintenance window and take a verified backup.
2. Install locked dependencies with `composer install --no-dev --classmap-authoritative`.
3. Run `php database/migrate.php` once. Migrations are additive and tracked in `migrations`; do not run schema files manually.
4. Ensure `public/uploads` and `storage/logs` exist and are writable only by the application service account. Do not grant global write permissions.
5. Run `php tests/run.php`, then lint PHP and changed JavaScript.
6. Restart PHP workers so opcode caches and environment values refresh, then end maintenance mode.

### PWA web-server requirements

Production must use HTTPS. Serve `/manifest.webmanifest` as `application/manifest+json` and serve
root `/sw.js` with `Cache-Control: no-cache, must-revalidate` and `Service-Worker-Allowed: /`.
Versioned `/assets/` files may use long-lived static caching, but dynamic PHP responses and private
downloads must retain `Cache-Control: no-store, private`. Confirm `APP_URL` is the canonical HTTPS
origin and the reverse proxy forwards the original scheme. Verify `/offline.html` and all three
`/assets/pwa/` icons are public. Bump the service-worker `VERSION` for cache-affecting releases.

## Security and operations

Configure cookies as Secure, HttpOnly and SameSite=Lax (or Strict where compatible), use short idle timeouts, rotate the session ID at authentication, and terminate sessions when accounts are disabled. Keep `display_errors` off; send PHP errors and application logs to protected, rotated storage. Monitor authentication failures, HTTP 5xx responses, failed migrations, disk space, database capacity, and backup age. SMS sending remains disabled until a real `SmsProviderInterface` adapter is selected and configured.
