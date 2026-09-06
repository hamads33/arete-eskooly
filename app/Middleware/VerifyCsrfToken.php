<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class VerifyCsrfToken
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!CSRF::validate((string) $request->input('_token', ''))) {
            return Response::html('CSRF token mismatch.', 419);
        }
        return $next($request);
    }
}
