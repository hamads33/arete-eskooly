# Greenfield Admin Dashboard — Core PHP Scaffold

The approved 50-page admin frontend running through a framework-free, extendable Core PHP 8.2+ architecture. The original static HTML remains in the repository as the visual baseline; the application entry point is now `public/index.php`.

## Run locally

From this folder:

```powershell
php scripts/setup-local.php
composer install
composer dump-autoload
docker compose up -d mysql
php database/migrate.php
php database/seed.php
php -S 127.0.0.1:8081 -t public public/router.php
```

For a new workstation or to continue with existing school data, follow
[the new computer guide](docs/NEW-COMPUTER.md). GitHub contains code; existing databases,
uploads, environment secrets and Android signing keys require a separate private transfer.

Open `http://127.0.0.1:8081/login` for the local development server.

See `docs/PRIORITY-DELIVERY-STATUS.md` for the implemented Phase 2–3 priority scope, verification results, and remaining work.

Set `DEV_ADMIN_EMAIL` and a unique `DEV_ADMIN_PASSWORD` of at least 12 characters in the uncommitted `.env` before seeding. The seed creates an administrator only when that email does not already exist.

Local development uses the dedicated MySQL 8.4 service in `compose.yaml`, exposed on host port `3307` to avoid conflicting with other local projects. Wait for `docker compose ps` to report the service healthy before migrating. Database data persists in the `greenfield_mysql_data` Docker volume.

For Laragon/Apache, create a virtual host whose document root is this project's `public` directory. Enable `mod_rewrite`; the provided `.htaccess` serves real assets directly and routes application requests through the front controller.

## Structure

```text
app/Core/         router, request/response, session, validation, CSRF and PDO
app/Controllers/  thin module controllers
app/Views/        shared layouts/partials and application page views
app/Middleware/   active auth/CSRF protection and RBAC middleware
config/           application, database, session, navigation and page metadata
public/           front controller, rewrite/router files and public assets
routes/           clean admin routes
storage/          logs, cache and non-public upload destination
tests/            lightweight foundation checks
docs/             architecture, route and backend-integration status
```

Legacy static HTML baselines, duplicate source assets, historical QA captures, and the completed one-time migration tooling are separated in `C:\Users\OGDCL\Desktop\arete-eskooly-reference-archive`. They are not required to run the application.

## Libraries

- Chart.js: responsive financial, fee-status, and class-strength charts.
- Lucide: consistent SVG interface icons.
- No PHP or JavaScript framework, build step, Bootstrap, Tailwind, React, Vue, or Angular.

## Demo data and PHP integration

Temporary application demo data is centralized in `DashboardController` and the `_demo` block of `config/pages.php`. Dashboard chart data is emitted safely with `json_encode` JSON hex flags. Remaining approved fixture markup is tracked as static/demo in `docs/BACKEND-INTEGRATION-STATUS.md`.

All 50 migrated pages use clean routes. Sidebar configuration lives only in `config/navigation.php`; active sections are derived from controller/page metadata and retain the maroon active state. Unbuilt static placeholders remain non-persisting UI links.

## Verification

Run the foundation checks with `php tests/run.php`. Route and browser verification should run against the PHP server, never the static server. See `docs/ARCHITECTURE.md`, `docs/ROUTES.md`, and `docs/BACKEND-INTEGRATION-STATUS.md`.

## Responsive behavior

- Large desktop: full sidebar, four KPI columns, split analytics rail.
- Small desktop: two-column KPI grid with compact spacing.
- Tablet (`<=1023px`): off-canvas sidebar and stacked analytics.
- Mobile (`<=767px`): compact header/search, stacked activity cards.
- Narrow mobile (`<=420px`): single-column KPIs and simplified controls.

Target QA sizes: 1920×1080, 1440×900, 1366×768, 1024×768, 768×1024, 390×844, and 360×800.
