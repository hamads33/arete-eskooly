<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class GuestMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (auth_user() === null) {
            return $next($request);
        }
        // Send each signed-in user to the area they can actually reach; bouncing a portal
        // user to /admin/dashboard would strand them on a 403.
        if (hasRole('admin')) {
            return Response::redirect(url('/admin/dashboard'));
        }
        if (hasRole('student')) {
            return Response::redirect(url('/portal/student'));
        }
        if (hasRole('employee')) {
            return Response::redirect(url('/portal/employee'));
        }
        return Response::redirect(url('/admin/dashboard'));
    }
}
