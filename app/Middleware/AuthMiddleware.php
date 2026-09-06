<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use Closure;
use App\Repositories\UserRepository;

final class AuthMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = auth_user();
        if ($sessionUser === null) {
            return Response::redirect(url('/login'));
        }
        $currentUser = (new UserRepository($this->app->database()))->findAuthContext((int) $sessionUser['id']);
        if ($currentUser === null || $currentUser['status'] !== 'active') {
            $this->app->session()->forget('auth_user');
            $this->app->session()->regenerate();
            return Response::redirect(url('/login'));
        }
        $this->app->session()->set('auth_user', $currentUser);
        return $next($request);
    }
}
