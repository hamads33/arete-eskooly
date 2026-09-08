<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Arete Coaching Center Admin',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8080', '/'),
    'timezone' => 'Asia/Karachi',

    /*
     * Live classes. The display timezone is the institute's own preference
     * (institute_preferences.timezone), not this value — see LiveClassService::timezone().
     *
     * join_before_start_minutes opens the student's Join button this many minutes before the
     * scheduled start (§23). It stays open until the meeting's end time.
     */
    'live_classes' => [
        'join_before_start_minutes' => (int) (getenv('JOIN_BEFORE_START_MINUTES') ?: 10),
        'default_instant_duration' => 60,
    ],
];
