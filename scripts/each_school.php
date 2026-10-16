<?php
declare(strict_types=1);
/**
 * Runs a CLI script for every signed-up school (each in its own process). The main portal is not
 * included; run the script for it directly as before.
 *
 *   php scripts/each_school.php database/migrate.php
 *   php scripts/each_school.php scripts/sms_daily.php
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';
$script = (string) ($argv[1] ?? '');
if ($script === '') { fwrite(STDERR, "Usage: php scripts/each_school.php <script.php>\n"); exit(2); }
$failed = 0;
foreach (glob(App\Platform\PlatformConfig::schoolsPath() . '/*/.env') ?: [] as $env) {
    $site = dirname($env);
    if (is_file($site . '/SUSPENDED') && $script !== 'database/migrate.php') continue;
    // Expired subscriptions: no fee runs, SMS or other jobs until renewed (migrations still run).
    if ($script !== 'database/migrate.php' && preg_match('/^SCHOOL_VALID_UNTIL="?(\d{4}-\d{2}-\d{2})/m', (string) file_get_contents($env), $m) && date('Y-m-d') > $m[1]) continue;
    echo '== ', basename($site), PHP_EOL;
    $process = proc_open([PHP_BINARY, __DIR__ . '/school.php', $site, $script], [1 => STDOUT, 2 => STDERR], $pipes, null, App\Platform\SchoolProvisioner::cleanEnvironment());
    $code = is_resource($process) ? proc_close($process) : 1;
    if ($code !== 0) { $failed++; echo "!! ", basename($site), " exited with {$code}", PHP_EOL; }
}
exit($failed > 0 ? 1 : 0);
