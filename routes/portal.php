<?php

declare(strict_types=1);

use App\Controllers\EmployeePortalController;
use App\Controllers\PortalAccountController;
use App\Controllers\PortalMessagesController;
use App\Controllers\PortalPdfController;
use App\Controllers\ParentPortalController;
use App\Controllers\StudentPortalController;
use App\Controllers\TeacherLiveClassController;
use App\Controllers\TeacherWorkspaceController;
use App\Controllers\StudentOnlineTestsController;
use App\Controllers\AttachmentPreviewController;
use App\Controllers\PortalAnnouncementsController;
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
$router->get('/portal/student/notes', [StudentPortalController::class, 'notes'], name: 'portal.student.notes');
$router->get('/portal/student/courses', [StudentPortalController::class, 'courses'], name: 'portal.student.courses');
$router->get('/portal/student/recorded-lectures', [StudentPortalController::class, 'recordedLectures'], name: 'portal.student.recorded-lectures');
$router->get('/portal/student/recorded-lectures/{id}', [StudentPortalController::class, 'recordedLecture']);
$router->get('/portal/student/recorded-lectures/{id}/video', [StudentPortalController::class, 'recordedLectureVideo']);
$router->get('/portal/student/documents', [StudentPortalController::class, 'documents'], name: 'portal.student.documents');
$router->get('/portal/student/online-tests', [StudentOnlineTestsController::class, 'index'], name: 'portal.student.online-tests');
$router->get('/portal/student/online-tests/{id}', [StudentOnlineTestsController::class, 'show'], name: 'portal.student.online-test');
$router->post('/portal/student/online-tests/{id}', [StudentOnlineTestsController::class, 'submit'], [VerifyCsrfToken::class]);
$router->get('/portal/student/online-tests/{id}/result', [StudentOnlineTestsController::class, 'result'], name: 'portal.student.online-test-result');
$router->get('/attachments/notes/{id}', [AttachmentPreviewController::class, 'note'], [AuthMiddleware::class]);
$router->get('/attachments/test-questions/{id}', [AttachmentPreviewController::class, 'question'], [AuthMiddleware::class]);
$router->get('/attachments/test-answers/{id}', [AttachmentPreviewController::class, 'answer'], [AuthMiddleware::class]);
$router->get('/attachments/test-answers/{id}/{index}', [AttachmentPreviewController::class, 'answer'], [AuthMiddleware::class]);
$router->get('/attachments/test-papers/{id}/{index}', [AttachmentPreviewController::class, 'paper'], [AuthMiddleware::class]);
$router->get('/attachments/test-marked/{id}/{index}', [AttachmentPreviewController::class, 'markedPaper'], [AuthMiddleware::class]);
$router->get('/attachments/announcements/{id}', [AttachmentPreviewController::class, 'announcement'], [AuthMiddleware::class]);
$router->get('/portal/announcements', [PortalAnnouncementsController::class, 'index'], name: 'portal.announcements');
// Live classes. The list renders no Meet URL: the join route re-checks audience, window and
// cancellation server-side before redirecting to Google (§24).
$router->get('/portal/student/live-classes', [StudentPortalController::class, 'liveClasses'], name: 'portal.student.live-classes');
$router->get('/portal/student/live-classes/{id}/join', [StudentPortalController::class, 'joinLiveClass'], name: 'portal.student.live-class-join');

// ---- employee --------------------------------------------------------------
$router->get('/portal/employee', [EmployeePortalController::class, 'dashboard'], name: 'portal.employee.dashboard');
$router->get('/portal/employee/profile', [EmployeePortalController::class, 'profile'], name: 'portal.employee.profile');
$router->get('/portal/employee/attendance', [EmployeePortalController::class, 'attendance'], name: 'portal.employee.attendance');
$router->get('/portal/employee/salary', [EmployeePortalController::class, 'salary'], name: 'portal.employee.salary');
$router->get('/portal/employee/classes', [EmployeePortalController::class, 'classes'], name: 'portal.employee.classes');
$router->get('/portal/employee/homework', [EmployeePortalController::class, 'homework'], name: 'portal.employee.homework');
$router->get('/portal/employee/documents', [EmployeePortalController::class, 'documents'], name: 'portal.employee.documents');

// ---- parent / guardian ------------------------------------------------------
$router->get('/portal/parent', [ParentPortalController::class, 'index'], name: 'portal.parent.dashboard');
$router->get('/portal/parent/profile', [ParentPortalController::class, 'profile'], name: 'portal.parent.profile');
$router->get('/portal/parent/children/{id}', [ParentPortalController::class, 'child'], name: 'portal.parent.child');

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
$router->post('/portal/notifications/read', [StudentPortalController::class, 'readNotifications'], [VerifyCsrfToken::class]);


// ---- teacher workspace (write access, scoped by teacher_assignments) --------
$router->get('/portal/employee/teaching', [TeacherWorkspaceController::class, 'index'], name: 'portal.teaching');
$router->get('/portal/employee/teaching/attendance', [TeacherWorkspaceController::class, 'attendance'], name: 'portal.teaching.attendance');
$router->post('/portal/employee/teaching/attendance', [TeacherWorkspaceController::class, 'saveAttendance'], [VerifyCsrfToken::class]);
$router->get('/portal/employee/teaching/homework', [TeacherWorkspaceController::class, 'homework'], name: 'portal.teaching.homework');
$router->post('/portal/employee/teaching/homework', [TeacherWorkspaceController::class, 'storeHomework'], [VerifyCsrfToken::class]);
$router->post('/portal/employee/teaching/homework/{id}/delete', [TeacherWorkspaceController::class, 'deleteHomework'], [VerifyCsrfToken::class]);
$router->get('/portal/employee/teaching/notes', [TeacherWorkspaceController::class, 'notes'], name: 'portal.teaching.notes');
$router->post('/portal/employee/teaching/notes', [TeacherWorkspaceController::class, 'storeNote'], [VerifyCsrfToken::class]);
$router->get('/portal/employee/teaching/tests', [TeacherWorkspaceController::class, 'tests'], name: 'portal.teaching.tests');
$router->post('/portal/employee/teaching/tests', [TeacherWorkspaceController::class, 'storeTest'], [VerifyCsrfToken::class]);
$router->get('/portal/employee/teaching/tests/{id}/marks', [TeacherWorkspaceController::class, 'testMarks'], name: 'portal.teaching.test-marks');
$router->post('/portal/employee/teaching/tests/{id}/marks', [TeacherWorkspaceController::class, 'saveTestMarks'], [VerifyCsrfToken::class]);

// Teacher-hosted Google Meet classes. The teacher holds no Google credentials: every call runs
// against the institute connection an administrator authorised, and the class/subject is
// re-checked against teacher_assignments on each write.
$router->get('/portal/employee/teaching/announcements', [TeacherWorkspaceController::class, 'announcements'], name: 'portal.teaching.announcements');
$router->post('/portal/employee/teaching/announcements', [TeacherWorkspaceController::class, 'storeAnnouncement'], [VerifyCsrfToken::class]);
$router->post('/portal/employee/teaching/announcements/{id}/delete', [TeacherWorkspaceController::class, 'deleteAnnouncement'], [VerifyCsrfToken::class]);
$router->get('/portal/employee/teaching/live-classes', [TeacherLiveClassController::class, 'index'], name: 'portal.teaching.live-classes');
$router->post('/portal/employee/teaching/live-classes', [TeacherLiveClassController::class, 'store'], [VerifyCsrfToken::class]);
$router->post('/portal/employee/teaching/live-classes/instant', [TeacherLiveClassController::class, 'instant'], [VerifyCsrfToken::class]);
$router->post('/portal/employee/teaching/live-classes/{id}', [TeacherLiveClassController::class, 'update'], [VerifyCsrfToken::class]);
$router->post('/portal/employee/teaching/live-classes/{id}/cancel', [TeacherLiveClassController::class, 'cancel'], [VerifyCsrfToken::class]);

$router->middlewarePrefix('/portal', AuthMiddleware::class);
$router->middlewarePrefix('/portal', PortalMiddleware::class);
// Suspended accounts: every page shows the restriction notice (students keep read-only fee pages).
$router->middlewarePrefix('/portal', \App\Middleware\AccountRestrictionMiddleware::class);
$router->middlewarePrefix('/attachments', \App\Middleware\AccountRestrictionMiddleware::class);
