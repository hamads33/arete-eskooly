<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Arete Coaching Center Admin',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8080', '/'),
    'timezone' => 'Asia/Karachi',
];
