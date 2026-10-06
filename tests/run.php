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
$roleAccessFeature = require BASE_PATH . '/tests/Feature/RoleAccessFeatureTest.php';
$checks = array_merge($checks, $roleAccessFeature($app));
$pwaFeature = require BASE_PATH . '/tests/Feature/PwaFeatureTest.php';
$checks = array_merge($checks, $pwaFeature($app));
$phaseFeature = require BASE_PATH . '/tests/Feature/SettingsAcademicsFeatureTest.php';
$checks = array_merge($checks, $phaseFeature($app));
$priorityFeature = require BASE_PATH . '/tests/Feature/PriorityModulesFeatureTest.php';
$checks = array_merge($checks, $priorityFeature($app));
$remainingFeature = require BASE_PATH . '/tests/Feature/RemainingWorkflowsFeatureTest.php';
$checks = array_merge($checks, $remainingFeature($app));
$onlineTestFeature = require BASE_PATH . '/tests/Feature/OnlineTestFeatureTest.php';
$checks = array_merge($checks, $onlineTestFeature($app));
$boundViews = require BASE_PATH . '/tests/Feature/BoundViewsFeatureTest.php';
$checks = array_merge($checks, $boundViews($app));
$generalSettings = require BASE_PATH . '/tests/Feature/GeneralSettingsCompletionTest.php';
$checks = array_merge($checks, $generalSettings($app));
$signupFeature = require BASE_PATH . '/tests/Feature/SignupFeatureTest.php';
$checks = array_merge($checks, $signupFeature($app));
$pdfReporting = require BASE_PATH . '/tests/Feature/PdfReportingFeatureTest.php';
$checks = array_merge($checks, $pdfReporting($app));
$personStatus = require BASE_PATH . '/tests/Feature/PersonStatusFeatureTest.php';
$checks = array_merge($checks, $personStatus($app));
$smsReliability = require BASE_PATH . '/tests/Feature/SmsReliabilityFeatureTest.php';
$checks = array_merge($checks, $smsReliability($app));
$sheetSlip = require BASE_PATH . '/tests/Feature/SalarySheetDefaultersSlipFeatureTest.php';
$checks = array_merge($checks, $sheetSlip($app));
$feeCollection = require BASE_PATH . '/tests/Feature/FeeCollectionFeatureTest.php';
$checks = array_merge($checks, $feeCollection($app));
$feeVouchers = require BASE_PATH . '/tests/Feature/FeeVouchersFeatureTest.php';
$checks = array_merge($checks, $feeVouchers($app));
$portalFeature = require BASE_PATH . '/tests/Feature/PortalFeatureTest.php';
$checks = array_merge($checks, $portalFeature($app));
$pakistanErp = require BASE_PATH . '/tests/Feature/PakistanErpMustHaveFeatureTest.php';
$checks = array_merge($checks, $pakistanErp($app));

$teacherWorkspace = require BASE_PATH . '/tests/Feature/TeacherWorkspaceFeatureTest.php';
$checks = array_merge($checks, $teacherWorkspace($app));

$academicStructure = require BASE_PATH . '/tests/Feature/AcademicStructureFeatureTest.php';
$checks = array_merge($checks, $academicStructure($app));

$googleMeet = require BASE_PATH . '/tests/Feature/GoogleMeetFeatureTest.php';
$checks = array_merge($checks, $googleMeet($app));

$whatsappOutreach = require BASE_PATH . '/tests/Feature/WhatsAppOutreachFeatureTest.php';
$checks = array_merge($checks, $whatsappOutreach($app));

$studentCsvCrud = require BASE_PATH . '/tests/Feature/StudentCsvCrudFeatureTest.php';
$checks = array_merge($checks, $studentCsvCrud($app));

$impersonation = require BASE_PATH . '/tests/Feature/ImpersonationFeatureTest.php';
$checks = array_merge($checks, $impersonation($app));

$lectureFolders = require BASE_PATH . '/tests/Feature/LectureFoldersNotificationsFeatureTest.php';
$checks = array_merge($checks, $lectureFolders($app));

$smsCenter = require BASE_PATH . '/tests/Feature/SmsCenterFeatureTest.php';
$checks = array_merge($checks, $smsCenter($app));

$accountAccess = require BASE_PATH . '/tests/Feature/AccountAccessFeatureTest.php';
$checks = array_merge($checks, $accountAccess($app));

$coursesScope = require BASE_PATH . '/tests/Feature/CoursesTeacherScopeFeatureTest.php';
$checks = array_merge($checks, $coursesScope($app));

$salaryAdvance = require BASE_PATH . '/tests/Feature/SalaryAdvanceFeatureTest.php';
$checks = array_merge($checks, $salaryAdvance($app));

$expenseReport = require BASE_PATH . '/tests/Feature/ExpenseReportFeatureTest.php';
$checks = array_merge($checks, $expenseReport($app));
$financeEdit = require BASE_PATH . '/tests/Feature/FinanceEditReversalFeatureTest.php';
$checks = array_merge($checks, $financeEdit($app));

$onlineTests = require BASE_PATH . '/tests/Feature/OnlineTestsFeatureTest.php';
$checks = array_merge($checks, $onlineTests($app));
$submissionPages = require BASE_PATH . '/tests/Feature/SubmissionPagesFeatureTest.php';
$checks = array_merge($checks, $submissionPages($app));
$onlineTestEdit = require BASE_PATH . '/tests/Feature/OnlineTestEditFeatureTest.php';
$checks = array_merge($checks, $onlineTestEdit($app));

foreach ($checks as $name => $result) {
    echo ($result ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
}

exit(in_array(false, $checks, true) ? 1 : 0);
