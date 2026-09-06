<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\GuestMiddleware;
use App\Middleware\VerifyCsrfToken;

$router->get('/login', [AuthController::class, 'create'], [GuestMiddleware::class], 'login');
$router->get('/signup', [AuthController::class, 'signup'], [GuestMiddleware::class], 'signup');
$router->post('/signup', [AuthController::class, 'register'], [GuestMiddleware::class, VerifyCsrfToken::class], 'signup.store');
$router->post('/login', [AuthController::class, 'store'], [GuestMiddleware::class, VerifyCsrfToken::class], 'login.store');
$router->post('/logout', [AuthController::class, 'destroy'], [VerifyCsrfToken::class], 'logout');
