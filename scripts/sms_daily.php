<?php
declare(strict_types=1);
/**
 * Daily SMS job: fee due / overdue reminders, and releasing messages held by gateways that went
 * silent. Safe to run more than once a day: every reminder is keyed per invoice, so nothing is
 * queued twice.
 *
 * Cron (daily at 09:00):  0 9 * * * php /path/to/app/scripts/sms_daily.php
 */
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';

use App\Services\Sms\{SmsAutomation, SmsQueue};

$db = $app->database();
$automation = new SmsAutomation($db);
$queue = new SmsQueue($db);
$total = 0;
foreach ($db->query('SELECT id FROM institutes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $institute) {
    $institute = (int) $institute;
    $queue->releaseStale($institute);
    $made = $automation->feeReminders($institute);
    $total += $made;
    echo "Institute {$institute}: {$made} fee reminder SMS queued\n";
}
echo "Total queued: {$total}\n";
