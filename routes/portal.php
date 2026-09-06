<?php

declare(strict_types=1);

use App\Controllers\EmployeePortalController;
use App\Controllers\PortalAccountController;
use App\Controllers\PortalMessagesController;
use App\Controllers\PortalPdfController;
use App\Controllers\StudentPortalController;
use App\Middleware\AuthMiddleware;
use App\Middleware\PortalMiddleware;
use App\Middleware\VerifyCsrfToken;

/**
 * Student and employee self-service portals.
 *
 * Deliberately outside the /admin prefix: routes/web.php applies AdminRoleMiddleware to the
 * whole of /admin, which would reject every portal user. Ownership is enforced per request in
 * PortalRepository — no route here accepts a student or employee identifier.
 */

/** @var App\Core\Router $router */

// ---- student ---------------------------------------------------------------
$router->get('/portal/student', [StudentPortalController::class, 'dashboard'], name: 'portal.student.dashboard');
$router->get('/portal/student/profile', [StudentPortalController::class, 'profile'], name: 'portal.student.profile');
$router->get('/portal/student/attendance', [StudentPortalController::class, 'attendance'], name: 'portal.student.attendance');
$router->get('/portal/student/fees', [StudentPortalController::class, 'fees'], name: 'portal.student.fees');
$router->get('/portal/student/fees/{id}', [StudentPortalController::class, 'invoice'], name: 'portal.student.invoice');
$router->get('/portal/student/results', [StudentPortalController::class, 'results'], name: 'portal.student.results');
$router->get('/portal/student/homework', [StudentPortalController::class, 'homework'], name: 'portal.student.homework');
$router->get('/portal/student/documents', [StudentPortalController::class, 'documents'], name: 'portal.student.documents');

// ---- employee --------------------------------------------------------------
$router->get('/portal/employee', [EmployeePortalController::class, 'dashboard'], name: 'portal.employee.dashboard');
$router->get('/portal/employee/profile', [EmployeePortalController::class, 'profile'], name: 'portal.employee.profile');
$router->get('/portal/employee/attendance', [EmployeePortalController::class, 'attendance'], name: 'portal.employee.attendance');
$router->get('/portal/employee/salary', [EmployeePortalController::class, 'salary'], name: 'portal.employee.salary');
$router->get('/portal/employee/classes', [EmployeePortalController::class, 'classes'], name: 'portal.employee.classes');
$router->get('/portal/employee/homework', [EmployeePortalController::class, 'homework'], name: 'portal.employee.homework');
$router->get('/portal/employee/documents', [EmployeePortalController::class, 'documents'], name: 'portal.employee.documents');

// ---- branded PDF documents (self-scoped) -----------------------------------
$router->get('/portal/student/documents/admission-letter', [PortalPdfController::class, 'admissionLetter'], name: 'portal.student.admission-letter');
$router->get('/portal/student/documents/fee-slip/{payment}', [PortalPdfController::class, 'feeSlip'], name: 'portal.student.fee-slip');
$router->get('/portal/student/documents/report-card/{exam}', [PortalPdfController::class, 'reportCard'], name: 'portal.student.report-card');
$router->get('/portal/employee/documents/job-letter', [PortalPdfController::class, 'jobLetter'], name: 'portal.employee.job-letter');
$router->get('/portal/employee/documents/salary-slip/{payment}', [PortalPdfController::class, 'salarySlip'], name: 'portal.employee.salary-slip');

// ---- shared: messaging and account settings --------------------------------
$router->get('/portal/messages', [PortalMessagesController::class, 'index'], name: 'portal.messages');
$router->get('/portal/messages/{id}', [PortalMessagesController::class, 'show'], name: 'portal.messages.show');
$router->post('/portal/messages', [PortalMessagesController::class, 'start'], [VerifyCsrfToken::class]);
$router->post('/portal/messages/{id}', [PortalMessagesController::class, 'reply'], [VerifyCsrfToken::class]);
$router->get('/portal/account', [PortalAccountController::class, 'edit'], name: 'portal.account');
$router->post('/portal/account', [PortalAccountController::class, 'update'], [VerifyCsrfToken::class]);

$router->middlewarePrefix('/portal', AuthMiddleware::class);
$router->middlewarePrefix('/portal', PortalMiddleware::class);
