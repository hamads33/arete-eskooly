<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Closure;

/**
 * Blocks modules that are not production ready.
 *
 * The sidebar shows these as locked and explains why, but a hand-typed URL must not slip past,
 * so the same rule is enforced server-side. Nothing is deleted — the underlying code and data
 * stay intact, ready to be switched on by removing the prefix in routes/web.php.
 */
final class PreviewModuleMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        return Response::html(
            View::render('errors/preview', [
                'pageTitle' => 'Under Testing',
                'moduleName' => $this->moduleName($request->path()),
            ], 'layouts/error'),
            503
        );
    }

    private function moduleName(string $path): string
    {
        return match (true) {
            str_contains($path, 'live-class') => 'Live Classes',
            str_contains($path, 'sms') => 'SMS Gateway',
            str_contains($path, 'certificates') => 'Certificates',
            default => 'This module',
        };
    }
}
