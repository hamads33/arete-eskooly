<?php

declare(strict_types=1);

use App\Core\CSRF;

if (!function_exists('csrf_token')) {
    function csrf_token(): string { return CSRF::token(); }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}
