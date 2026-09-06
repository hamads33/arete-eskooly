<?php

declare(strict_types=1);

use App\Core\CSRF;
use App\Core\Request;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;

return static function (\App\Core\App $app): array {
    $checks = [];
    $request = static fn (string $method, string $uri, array $body = []): Request => new Request([], $body, [], ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
    $auth = new AuthService(new UserRepository($app->database()), $app->session());
    $email = (string) getenv('DEV_ADMIN_EMAIL');
    $password = (string) getenv('DEV_ADMIN_PASSWORD');

    $auth->logout();
    $guestResponse = $app->run($request('GET', '/admin/dashboard'));
    $checks['guest admin request redirects'] = $guestResponse->status() === 302 && ($guestResponse->headers()['Location'] ?? '') === url('/login');
    $checks['unknown email is rejected'] = $auth->attempt('missing@example.invalid', 'irrelevant-password') === 'invalid';
    $checks['invalid password is rejected'] = $auth->attempt($email, 'incorrect-password') === 'invalid';

    $pdo = $app->database()->connection();
    $pdo->beginTransaction();
    $now = date('Y-m-d H:i:s');
    $insert = $pdo->prepare("INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,:password,'disabled',:created,:updated)");
    $insert->execute(['name' => 'Disabled Test User', 'email' => 'disabled-test@greenfield.local', 'password' => password_hash('Disabled-Test-2026!', PASSWORD_DEFAULT), 'created' => $now, 'updated' => $now]);
    $checks['disabled user is blocked'] = $auth->attempt('disabled-test@greenfield.local', 'Disabled-Test-2026!') === 'disabled';
    $restrictedInsert=$pdo->prepare("INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,:password,'active',:created,:updated)");
    $restrictedInsert->execute(['name' => 'Restricted Test User', 'email' => 'restricted-test@greenfield.local', 'password' => password_hash('Restricted-Test-2026!', PASSWORD_DEFAULT), 'created' => $now, 'updated' => $now]);
    $checks['non-admin login is restricted'] = $auth->attempt('restricted-test@greenfield.local', 'Restricted-Test-2026!') === 'restricted' && guest();
    $pdo->rollBack();

    $beforeId = session_id();
    $checks['valid login succeeds'] = $auth->attempt($email, $password) === 'authenticated';
    $checks['session regenerates at login'] = session_id() !== '' && session_id() !== $beforeId;
    $checks['current user helper works'] = check() && user()['email'] === $email;
    $checks['role helper works'] = hasRole('admin');
    // The admin role now holds the '*' wildcard, so it satisfies every permission by design.
    $checks['permission helper works'] = can('dashboard.view') && can('unassigned.permission') && can('*');
    $adminContext = $app->session()->get('auth_user');
    $app->session()->set('auth_user', array_replace($adminContext, ['roles' => ['teacher'], 'permissions' => ['dashboard.view']]));
    $checks['wildcard is required for unmapped permission'] = can('dashboard.view') && !can('unassigned.permission');
    $permissionRequest = $request('GET', '/admin/dashboard');
    $permissionRequest->setRouteParameters(['permission' => 'unassigned.permission']);
    $denied = (new RoleMiddleware($app))->handle($permissionRequest, static fn (): \App\Core\Response => \App\Core\Response::html('allowed'));
    $checks['unauthorized permission is denied'] = $denied->status() === 403;
    $app->session()->set('auth_user', $adminContext);
    $permissionRequest->setRouteParameters(['permission' => 'dashboard.view']);
    $allowed = (new RoleMiddleware($app))->handle($permissionRequest, static fn (): \App\Core\Response => \App\Core\Response::html('allowed'));
    $checks['authorized permission is allowed'] = $allowed->status() === 200;
    $checks['authenticated admin route resolves'] = $app->run($request('GET', '/admin/dashboard'))->status() === 200;
    $checks['guest middleware redirects authenticated login page'] = $app->run($request('GET', '/login'))->status() === 302;

    $auth->logout();
    $checks['logout clears authentication'] = guest();
    $checks['invalid CSRF is rejected'] = $app->run($request('POST', '/login', ['email' => $email, 'password' => $password, '_token' => 'invalid']))->status() === 419;
    $token = CSRF::token();
    $validLogin = $app->run($request('POST', '/login', ['email' => $email, 'password' => $password, '_token' => $token]));
    $checks['CSRF-protected login succeeds'] = $validLogin->status() === 302 && check();
    $logout = $app->run($request('POST', '/logout', ['_token' => CSRF::token()]));
    $checks['CSRF-protected logout succeeds'] = $logout->status() === 302 && guest();

    return $checks;
};
