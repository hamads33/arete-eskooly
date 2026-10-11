<?php

declare(strict_types=1);

// Routes that exist only on signed-up school sites (SCHOOL_SLUG is set in their .env).
use App\Controllers\PlatformSupportLoginController;

// Password-free sign-in started from the Arete admin portal; the single-use token is the credential.
$router->post('/platform-login', [PlatformSupportLoginController::class, 'login']);
