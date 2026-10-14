<?php

declare(strict_types=1);

// School sign-up platform. Loaded only on the main portal with PLATFORM_ENABLED=true.
// Approvals live in the admin area, so the /admin auth and admin-only middleware apply.
use App\Controllers\PlatformController;
use App\Controllers\SchoolSignupController;
use App\Core\Response;
use App\Middleware\VerifyCsrfToken;

$router->get('/register-school', [SchoolSignupController::class, 'create'], name: 'school.register');
$router->post('/register-school', [SchoolSignupController::class, 'store'], [VerifyCsrfToken::class]);
$router->get('/register-school/check', [SchoolSignupController::class, 'check']);
$router->get('/school-status', [SchoolSignupController::class, 'status']);
$router->get('/admin/schools', [PlatformController::class, 'index']);
$router->get('/admin/schools/{id}/features', [PlatformController::class, 'features']);
$router->post('/admin/schools/{id}/{action}', [PlatformController::class, 'action'], [VerifyCsrfToken::class]);
$router->get('/platform', static fn() => Response::redirect(url('/admin/schools')));
