<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Validator;

/** @var App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$checks = [];
$check = static function (string $name, bool $result) use (&$checks): void { $checks[$name] = $result; };

$check('escape helper', e('<script>') === '&lt;script&gt;');
$check('url helper', str_ends_with(url('/admin/dashboard'), '/admin/dashboard'));
$check('asset helper', str_ends_with(asset('css/base.css'), '/assets/css/base.css'));
$token = CSRF::token();
$check('csrf generation and validation', strlen($token) === 64 && CSRF::validate($token));
$validator = new Validator();
$check('validator accepts valid email', $validator->validate(['email' => 'admin@example.com'], ['email' => 'required|email']));
$check('validator rejects missing required value', !$validator->validate([], ['name' => 'required']));
$app->session()->set('auth_user', ['id' => 1, 'name' => 'Local Administrator', 'email' => 'admin@greenfield.local', 'status' => 'active', 'roles' => ['admin'], 'permissions' => ['dashboard.view']]);
$known = $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/dashboard']));
$unknown = $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/missing']));
$check('known route resolves', $known->status() === 200);
$check('dashboard uses live metrics', str_contains($known->content(), 'Active enrolments') && !str_contains($known->content(), '1,248'));
$check('dashboard refresh control is wired', str_contains($known->content(), 'data-dashboard-refresh'));
$check('unknown route returns 404', $unknown->status() === 404);

$authFeature = require BASE_PATH . '/tests/Feature/AuthFeatureTest.php';
$checks = array_merge($checks, $authFeature($app));
$phaseFeature = require BASE_PATH . '/tests/Feature/SettingsAcademicsFeatureTest.php';
$checks = array_merge($checks, $phaseFeature($app));
$priorityFeature = require BASE_PATH . '/tests/Feature/PriorityModulesFeatureTest.php';
$checks = array_merge($checks, $priorityFeature($app));
$remainingFeature = require BASE_PATH . '/tests/Feature/RemainingWorkflowsFeatureTest.php';
$checks = array_merge($checks, $remainingFeature($app));
$boundViews = require BASE_PATH . '/tests/Feature/BoundViewsFeatureTest.php';
$checks = array_merge($checks, $boundViews($app));
$generalSettings = require BASE_PATH . '/tests/Feature/GeneralSettingsCompletionTest.php';
$checks = array_merge($checks, $generalSettings($app));
$signupFeature = require BASE_PATH . '/tests/Feature/SignupFeatureTest.php';
$checks = array_merge($checks, $signupFeature($app));
$pdfReporting = require BASE_PATH . '/tests/Feature/PdfReportingFeatureTest.php';
$checks = array_merge($checks, $pdfReporting($app));
$portalFeature = require BASE_PATH . '/tests/Feature/PortalFeatureTest.php';
$checks = array_merge($checks, $portalFeature($app));

foreach ($checks as $name => $result) {
    echo ($result ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
}

exit(in_array(false, $checks, true) ? 1 : 0);
