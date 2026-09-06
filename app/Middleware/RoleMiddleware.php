<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Closure;

final class RoleMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $permission = (string) $request->route('permission', '');
        if ($permission !== '' && !can($permission)) {
            return Response::html(View::render('errors/403', ['pageTitle' => 'Access Denied'], 'layouts/error'), 403);
        }
        return $next($request);
    }
}
