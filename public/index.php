<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;

define('BASE_PATH', dirname(__DIR__));

$autoload = BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Composer autoload is missing. Run: composer install';
    exit;
}

require $autoload;

/** @var App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$app->run(Request::capture())->send();
