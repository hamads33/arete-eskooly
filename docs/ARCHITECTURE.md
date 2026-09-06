# Core PHP Architecture

## Request lifecycle

1. Apache/Laragon rewrite rules, or `public/router.php` with PHP's built-in server, send application URLs to `public/index.php`.
2. The front controller loads Composer, bootstraps environment/configuration and starts the secure session.
3. `Router` matches the HTTP method and clean path, applies prefix and route middleware, and resolves route parameters. Every `/admin/*` route passes through `AuthMiddleware`.
4. A module controller selects centrally defined page metadata and temporary demo data.
5. `View` captures the page view and injects it into the shared admin layout.
6. `Response` applies the status/headers and sends the final HTML.

Production exceptions are logged to `storage/logs/app.log` and rendered through the maroon 500 page. Stack details appear only when `APP_DEBUG=true`.

## Responsibilities

- `app/Core`: framework-free application primitives—application container, router, controller, view, request, response, session, CSRF, validation and lazy PDO connection.
- `app/Controllers`: module boundaries. Controllers receive requests and prepare view data; they do not contain HTML or SQL.
- `app/Views`: page-only views plus shared layouts, partials, small components and error pages.
- `app/Middleware`: active authentication and CSRF middleware plus guest and permission/role foundations. Admin routes are protected; login is guest-only.
- `app/Helpers`: URL/asset routing, escaping, view, CSRF, old input/error and permission helpers.
- `app/Support`: flash/toast messages, pagination, safe upload policy and lightweight logging.
- `config/navigation.php`: single source for the sidebar, route targets and future `locked`/permission metadata.
- `config/pages.php`: route/view metadata, page assets and the clearly marked temporary demo-data location.
- `public/assets`: the approved CSS, JavaScript and local browser libraries. Only `public` should be web-accessible.
- `app/Repositories` and `app/Services`: reserved for real persistence and use-case logic once each backend module is approved. Empty wrappers were intentionally not created.

## Views and assets

The 50 page views contain approved page-specific markup only. `layouts/admin.php` owns the document, header, sidebar, toast/modal roots and asset loading. Each page declares its exact CSS/JS list in `config/pages.php`; the layout de-duplicates the list and loads no unrelated page bundle.

Dynamic output must use `e()`. Structured JavaScript data must use `json_encode` with the JSON hex flags, as demonstrated by `dashboardData`.

## Adding a page

1. Add a kebab-case view beneath the appropriate module directory.
2. Add its metadata to `config/pages.php`, including active menu keys and only required assets.
3. Add a focused action to that module's controller.
4. Register a clean named route in `routes/web.php`.
5. Add the link once in `config/navigation.php` and update route/integration documentation.
6. Add a feature smoke check and test all target viewport sizes.

## Adding backend behavior later

Introduce a migration/model only when the data contract is approved. Put prepared PDO queries in a repository, business orchestration in a service, and request validation/authorization in the controller or middleware. A state-changing route must use an appropriate HTTP verb, `VerifyCsrfToken`, validation, permission checks and flash/redirect feedback. Do not query the database from views.

Phase 2 uses `SettingsRepository` and `SettingsService` for settings persistence, transactional fee/grading writes, and rich-text sanitization. Phase 3 uses `AcademicRepository` and `AcademicService` for classes, subjects, duplicate prevention, and transactional assignments. Neither layer introduces Student or Employee domain tables ahead of their approved phases.

Uploads must pass `UploadPolicy` MIME, extension and size checks, use a generated filename and live under `storage/uploads` (not as an executable public file).

## Authentication and RBAC

`AuthController` validates login input and delegates credential checks to `AuthService`. `UserRepository` owns user, role and permission queries. Passwords use PHP's `PASSWORD_DEFAULT`; successful login and logout regenerate the session identifier. The session stores only the user's safe identity, status, role slugs and permission slugs. `AuthMiddleware` refreshes this context from the database on every protected request so disabled users and changed permissions take effect immediately.

The non-destructive migration runner records executed migrations in `migrations`. Auth tables are `users`, `roles`, `permissions`, `user_roles`, and `role_permissions`. The local seed reads development credentials only from environment values and does not overwrite an existing administrator.

## Windows, Laragon and deployment

The application uses portable relative paths based on `BASE_PATH`. For PHP's built-in server use `public/router.php`. For Laragon/Apache, point the virtual host document root at `public`; `.htaccess` preserves physical assets and routes everything else to the front controller. Set environment values in an uncommitted `.env` based on `.env.example`.
