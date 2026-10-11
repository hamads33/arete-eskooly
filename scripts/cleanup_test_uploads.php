<?php
declare(strict_types=1);
/**
 * Removes student answer images older than the admin's retention period (Online Tests →
 * Upload settings, default 30 days). Only already-marked papers are cleared. Safe to re-run.
 *
 * Cron (daily at 03:40):  40 3 * * * php /path/to/app/scripts/cleanup_test_uploads.php
 */
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';

$cleanup = new App\Services\TestUploadCleanup($app->database());
foreach ($app->database()->query('SELECT id FROM institutes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $institute) {
    echo date('Y-m-d H:i:s'), " institute {$institute}: ", $cleanup->run((int) $institute), " file(s) removed\n";
}
