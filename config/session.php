<?php

declare(strict_types=1);

return [
    'name' => getenv('SESSION_NAME') ?: 'greenfield_admin_session',
    'lifetime' => 7200,
    'secure' => filter_var(getenv('SESSION_SECURE') ?: ((getenv('APP_ENV') ?: 'production') === 'production' ? 'true' : 'false'), FILTER_VALIDATE_BOOL),
    'http_only' => true,
    'same_site' => 'Lax',
];
