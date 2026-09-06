<?php

declare(strict_types=1);

namespace App\Support;

final class Logger
{
    public function __construct(private readonly string $file)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $safeContext = array_diff_key($context, array_flip(['password', 'token', 'authorization', 'cookie']));
        $directory = dirname($this->file);
        if (!is_dir($directory)) @mkdir($directory, 0775, true);
        $line=sprintf("[%s] ERROR %s %s", date(DATE_ATOM), $message, json_encode($safeContext));
        if(is_dir($directory)&&is_writable($directory))error_log($line.PHP_EOL,3,$this->file);else error_log($line);
    }
}
