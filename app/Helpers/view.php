<?php

declare(strict_types=1);

use App\Core\View;

if (!function_exists('view')) {
    function view(string $name, array $data = [], string $layout = 'layouts/admin'): string
    {
        return View::render($name, $data, $layout);
    }
}

if (!function_exists('school_slug')) {
    /** The subdomain this copy serves (set in a school's own .env), or '' for the main Arete portal. */
    function school_slug(): string
    {
        return trim((string) (getenv('SCHOOL_SLUG') ?: ''));
    }
}

if (!function_exists('brand')) {
    /**
     * Name and logo for the public shell (login, install, error pages, favicons). The main portal
     * keeps its fixed Arete branding; a signed-up school shows its own name and uploaded logo.
     */
    function brand(): array
    {
        static $brand = null;
        if ($brand !== null) return $brand;
        if (school_slug() === '') {
            return $brand = ['name' => 'Arete Coaching Center', 'short' => 'Arete', 'caption' => 'A PROJECT OF USAMA EDUCATION SYSTEM', 'logo' => asset('images/arete-logo.png'), 'school' => false];
        }
        $row = [];
        try { $row = app()->database()->query('SELECT name,target_line,logo_path FROM institutes ORDER BY id LIMIT 1')->fetch() ?: []; } catch (\Throwable) {}
        $name = trim((string) ($row['name'] ?? '')) ?: (string) app()->config('app.name', 'School');
        $logo = trim((string) ($row['logo_path'] ?? ''));
        return $brand = [
            'name' => $name,
            'short' => mb_substr(explode(' ', $name)[0], 0, 12),
            'caption' => mb_strtoupper(trim((string) ($row['target_line'] ?? ''))),
            'logo' => $logo !== '' ? url('/' . ltrim($logo, '/')) : asset('images/school-logo.svg'),
            'school' => true,
        ];
    }
}
