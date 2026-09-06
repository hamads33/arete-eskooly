<?php

declare(strict_types=1);

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $values = app()->session()->flashed('_old', []);
        return is_array($values) ? ($values[$key] ?? $default) : $default;
    }
}

if (!function_exists('error')) {
    function error(string $key, mixed $default = null): mixed
    {
        $errors = app()->session()->flashed('_errors', []);
        return is_array($errors) ? ($errors[$key][0] ?? $default) : $default;
    }
}
