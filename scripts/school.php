<?php
declare(strict_types=1);
/**
 * Runs a CLI script for one signed-up school: BASE_PATH becomes that school's site folder, so the
 * script sees the school's .env, database and uploads.
 *
 *   php scripts/school.php /var/www/schools/abc database/migrate.php
 */
[$self, $site, $script] = array_pad($argv, 3, '');
$site = rtrim(str_replace('\\', '/', (string) $site), '/');
if ($site === '' || $script === '' || !is_file($site . '/.env') || !preg_match('#^[A-Za-z0-9_/.-]+\.php$#', $script) || str_contains($script, '..')) {
    fwrite(STDERR, "Usage: php scripts/school.php <school-site-folder> <script.php>\n");
    exit(2);
}
define('BASE_PATH', $site);
// Never inherit the caller's settings (the main portal's database, platform credentials): load
// this school's .env over the top and drop every key that is not in it.
$schoolEnv = [];
foreach (file($site . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $schoolEnv[$key] = stripcslashes(trim($value, '"'));
}
foreach (array_keys(getenv()) as $key) {
    if (preg_match('/^(APP_|DB_|SESSION_|PLATFORM_|GOOGLE_|SMS_|SCHOOL_|DEV_|PWA_|JOIN_)/', $key) && !isset($schoolEnv[$key])) { putenv($key); unset($_ENV[$key]); }
}
foreach ($schoolEnv as $key => $value) { putenv("{$key}={$value}"); $_ENV[$key] = $value; }
if (($schoolEnv['SCHOOL_SLUG'] ?? '') === '') { fwrite(STDERR, "{$site}/.env has no SCHOOL_SLUG; refusing to run.
"); exit(2); }
$argv = array_merge([$script], array_slice($argv, 3));
require BASE_PATH . '/' . $script;
