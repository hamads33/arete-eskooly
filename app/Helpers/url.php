<?php

declare(strict_types=1);

use App\Core\App;

if (!function_exists('app')) {
    function app(): App { return App::instance(); }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) app()->config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $clean=ltrim($path,'/');
        if ((string)app()->config('app.env','')==='production') {
            $filePart=(string)(parse_url($clean,PHP_URL_PATH)?:$clean);
            $file=BASE_PATH.'/public/assets/'.$filePart;
            if(is_file($file))$clean.=(str_contains($clean,'?')?'&':'?').'m='.filemtime($file);
        }
        return url('/assets/'.$clean);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $parameters = []): string { return url(app()->router()->named($name, $parameters)); }
}
