<?php
declare(strict_types=1);

// Safe to rerun: never overwrite an existing environment or its encryption key.
$root = dirname(__DIR__);
$env = $root . '/.env';
if (!is_file($env)) {
    $template = file_get_contents($root . '/.env.example');
    if ($template === false) throw new RuntimeException('Cannot read .env.example');
    $template = str_replace('replace-with-at-least-32-random-bytes', bin2hex(random_bytes(32)), $template);
    $handle = fopen($env, 'x');
    if ($handle === false) throw new RuntimeException('Cannot create .env');
    fwrite($handle, $template);
    fclose($handle);
    chmod($env, 0600);
    echo "Created .env with a new APP_KEY. Set DEV_ADMIN_PASSWORD before seeding.\n";
} else {
    echo "Kept existing .env and APP_KEY.\n";
}
foreach (['storage/logs', 'storage/cache', 'storage/uploads', 'storage/tmp', 'storage/sessions', 'public/uploads'] as $folder) {
    $path = $root . '/' . $folder;
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create ' . $folder);
    }
    if (!is_writable($path)) throw new RuntimeException('Not writable: ' . $folder);
}
$missing = [];
foreach (['pdo_mysql', 'mbstring', 'gd', 'openssl', 'fileinfo', 'dom', 'curl', 'zip'] as $extension) {
    if (!extension_loaded($extension)) $missing[] = $extension;
}
if (PHP_VERSION_ID < 80200 || $missing) {
    fwrite(STDERR, 'Requires PHP 8.2+ and extensions: ' . implode(', ', $missing) . "\n");
    exit(1);
}
echo "Runtime folders and PHP extensions ready. Next: composer install; see docs/NEW-COMPUTER.md.\n";
