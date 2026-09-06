<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;
use App\Support\Logger;

require_once BASE_PATH . '/bootstrap/environment.php';

$config = [
    'app' => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
    'session' => require BASE_PATH . '/config/session.php',
    'navigation' => require BASE_PATH . '/config/navigation.php',
    'pages' => require BASE_PATH . '/config/pages.php',
    'permissions' => require BASE_PATH . '/config/permissions.php',
];

$session = new Session($config['session']);
$session->start();

$router = new Router();
$database = new Database($config['database']);
$logger = new Logger(BASE_PATH . '/storage/logs/app.log');
$app = new App($router, $session, $database, $logger, $config);

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/portal.php';
require BASE_PATH . '/routes/auth.php';

return $app;
