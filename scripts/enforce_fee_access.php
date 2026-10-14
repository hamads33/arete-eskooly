<?php
declare(strict_types=1);
/**
 * Applies the unpaid-fee login rule (Fee Automation → suspend / block after N months) to every
 * student, and restores access for students who have paid. Safe to run any number of times.
 *
 * Cron (daily at 06:00):  0 6 * * * php /path/to/app/scripts/enforce_fee_access.php
 */
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';
if (\App\Platform\Features::isLocked('fees')) { echo "Skipped: fee access rules are not in this school's plan.", PHP_EOL; exit(0); }

$access = new App\Services\AccountAccess($app->database());
foreach ($app->database()->query('SELECT id FROM institutes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $institute) {
    $x = $access->evaluateInstitute((int) $institute);
    echo "Institute {$institute}: {$x['suspended']} suspended, {$x['blocked']} blocked, {$x['restored']} restored\n";
}
