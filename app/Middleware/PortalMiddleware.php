<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Closure;

/**
 * Gates the /portal prefix to users holding a portal role. AuthMiddleware runs first and
 * guarantees an active session; this only decides whether the signed-in user belongs here.
 */
final class PortalMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!hasRole('student') && !hasRole('employee') && !hasRole('parent')) {
            return Response::html(View::render('errors/403', ['pageTitle' => 'Access Denied'], 'layouts/error'), 403);
        }
        return $next($request);
    }
}
