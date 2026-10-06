<?php

declare(strict_types=1);

return [
    'name' => getenv('SESSION_NAME') ?: 'greenfield_admin_session',
    'lifetime' => 7200,
    'remember_lifetime' => 60 * 60 * 24 * 30,
    // App-owned folder (see Session::useOwnSavePath); empty string = use php.ini's session.save_path.
    'save_path' => getenv('SESSION_SAVE_PATH') !== false ? (string) getenv('SESSION_SAVE_PATH') : BASE_PATH . '/storage/sessions',
    'secure' => filter_var(getenv('SESSION_SECURE') ?: ((getenv('APP_ENV') ?: 'production') === 'production' ? 'true' : 'false'), FILTER_VALIDATE_BOOL),
    'http_only' => true,
    'same_site' => 'Lax',
];
