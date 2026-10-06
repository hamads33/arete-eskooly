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
            if (str_contains((string) $request->header('Accept', ''), 'application/json')) {
                // An in-page upload must not silently follow a redirect to the sign-in page.
                return Response::json(['ok' => false, 'reason' => 'signed_out', 'message' => 'You were signed out. Your pages are still saved on this device — sign in again and open this test to submit them.'], 401);
            }
            return Response::redirect(url('/login'));
        }
        $currentUser = (new UserRepository($this->app->database()))->findAuthContext((int) $sessionUser['id']);
        if ($currentUser === null || !in_array($currentUser['status'], ['active', 'suspended'], true)) {
            $this->app->session()->forget('auth_user');
            $this->app->session()->regenerate();
            return Response::redirect(url('/login'));
        }
        // A student / employee set to inactive or suspended while signed in is signed out if the admin's rule blocks sign-in.
        if (!in_array('admin', (array) ($currentUser['roles'] ?? []), true) && $this->app->session()->get('impersonator_user_id') === null
            && !\App\Support\PersonStatus::loginAllowed($this->app->database(), (int) $currentUser['id'])) {
            $this->app->session()->forget('auth_user');
            $this->app->session()->regenerate();
            \App\Support\Flash::add('error', 'Your account is not active at the moment. Please contact the institute administration.');
            return Response::redirect(url('/login'));
        }
        $this->app->session()->set('auth_user', $currentUser);
        return $next($request);
    }
}
