<?php

declare(strict_types=1);

use App\Core\View;

if (!function_exists('view')) {
    function view(string $name, array $data = [], string $layout = 'layouts/admin'): string
    {
        return View::render($name, $data, $layout);
    }
}
