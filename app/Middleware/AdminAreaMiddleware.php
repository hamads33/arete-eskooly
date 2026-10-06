<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Closure;

/**
 * Permission gate for the whole /admin area.
 *
 * Replaces the blanket "admin role only" rule that previously made the product single-role.
 * The required permission is derived from the first path segment after /admin and the HTTP
 * method, using config/permissions.php. A module with no entry stays admin-only, so any route
 * added later is closed by default rather than accidentally exposed.
 */
final class AdminAreaMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (can('*')) {
            return $next($request);
        }

        $permission = $this->requiredPermission($request);
        if ($permission !== null && can($permission)) {
            return $next($request);
        }

        // Reading the dashboard is open to anyone who holds any admin module: it is the shell
        // staff land on and every breadcrumb links back to, and DashboardController releases each
        // figure on it only to the permission for the module that figure comes from. Writes to it
        // (the statistics rebuild) still need dashboard.view.
        if ($this->module($request) === 'dashboard' && strtoupper($request->method()) === 'GET' && has_admin_access()) {
            return $next($request);
        }

        return Response::html(View::render('errors/403', ['pageTitle' => 'Access Denied'], 'layouts/error'), 403);
    }

    private function module(Request $request): string
    {
        $segments = array_values(array_filter(explode('/', $request->path())));
        return $segments[1] ?? 'dashboard';
    }

    /** Null means "no mapping", which the caller treats as admin-only. */
    private function requiredPermission(Request $request): ?string
    {
        $map = $this->app->config('permissions.' . $this->module($request));
        if (!is_array($map)) {
            return null;
        }
        $action = strtoupper($request->method()) === 'GET' ? 'view' : 'manage';
        return $map[$action] ?? null;
    }
}
