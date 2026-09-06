# Routes

## Public authentication

Public institute registration is temporarily closed. GET /signup displays the coming-soon notice without a form. POST /signup returns 403 after the existing guest/CSRF middleware and never invokes registration or authentication. The internal registration implementation described below is retained for a future launch.

`GET /login` and `GET /signup` share the original Arete maroon auth layout and local SVG artwork. Both use GuestMiddleware.
`POST /signup` uses GuestMiddleware and VerifyCsrfToken. It validates name, institute name, normalized unique email and matching passwords (8–72 characters), then transactionally creates the institute, user, admin role assignment, institute membership, preferences and default account head. AuthService signs in the owner and regenerates the session before redirecting to `/admin/settings/institute-profile`.
Login retains its existing routes, password verification, active-account checks and session rotation. Password values are never restored into forms.

All admin routes are clean GET routes served by `public/index.php`. All `/admin/*` routes are protected by `AuthMiddleware`; login is guest-only and both authentication POST routes require CSRF validation.

| Method | URL | Controller | Action | Middleware | Status |
|---|---|---|---|---|---|
| GET | `/` | Closure | redirect to dashboard | None | Working |
| GET | `/admin/dashboard` | DashboardController | index | AuthMiddleware | Protected and working |
| GET | `/admin/settings/account` | SettingsController | account | AuthMiddleware | Protected and working |
| GET | `/admin/settings/institute-profile` | SettingsController | instituteProfile | AuthMiddleware | Protected and working |
| GET | `/admin/settings/fee-particulars` | SettingsController | feeParticulars | AuthMiddleware | Protected and working |
| GET | `/admin/settings/fee-invoice-accounts` | SettingsController | feeInvoiceAccounts | AuthMiddleware | Protected and working |
| GET | `/admin/settings/rules-regulations` | SettingsController | rulesRegulations | AuthMiddleware | Protected and working |
| GET | `/admin/settings/marks-grading` | SettingsController | marksGrading | AuthMiddleware | Protected and working |
| GET | `/admin/classes` | ClassesController | index | AuthMiddleware | Protected and working |
| GET | `/admin/classes/create` | ClassesController | create | AuthMiddleware | Protected and working |
| GET | `/admin/subjects` | SubjectsController | index | AuthMiddleware | Protected and working |
| GET | `/admin/subjects/assign` | SubjectsController | assign | AuthMiddleware | Protected and working |
| POST | `/admin/settings/account` | SettingsController | updateAccount | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/settings/institute-profile` | SettingsController | updateInstituteProfile | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/settings/fee-particulars` | SettingsController | saveFeeParticulars | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/settings/fee-invoice-accounts` | SettingsController | addFeeInvoiceAccount | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/settings/fee-invoice-accounts/{id}/delete` | SettingsController | deleteFeeInvoiceAccount | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/settings/rules-regulations` | SettingsController | saveRules | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/settings/marks-grading` | SettingsController | saveMarksGrading | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/classes` | ClassesController | store | AuthMiddleware, VerifyCsrfToken | Working |
| POST | `/admin/subjects` | SubjectsController | store | AuthMiddleware, VerifyCsrfToken | Working |
| GET | `/admin/students` | StudentsController | index | AuthMiddleware | Protected and working |
| GET | `/admin/students/create` | StudentsController | create | AuthMiddleware | Protected and working |
| GET | `/admin/students/admission-letter` | StudentsController | admissionLetter | AuthMiddleware | Protected and working |
| GET | `/admin/students/id-cards` | StudentsController | idCards | AuthMiddleware | Protected and working |
| GET | `/admin/students/basic-list` | StudentsController | basicList | AuthMiddleware | Protected and working |
| GET | `/admin/students/logins` | StudentsController | logins | AuthMiddleware | Protected and working |
| GET | `/admin/students/promotions` | StudentsController | promotions | AuthMiddleware | Protected and working |
| GET | `/admin/students/report-card` | StudentsController | reportCard | AuthMiddleware | Protected and working |
| GET | `/admin/employees` | EmployeesController | index | AuthMiddleware | Protected and working |
| GET | `/admin/employees/create` | EmployeesController | create | AuthMiddleware | Protected and working |
| GET | `/admin/employees/job-letter` | EmployeesController | jobLetter | AuthMiddleware | Protected and working |
| GET | `/admin/employees/logins` | EmployeesController | logins | AuthMiddleware | Protected and working |
| GET | `/admin/accounts` | AccountsController | chart | AuthMiddleware | Protected and working |
| GET | `/admin/accounts/income/create` | AccountsController | incomeCreate | AuthMiddleware | Protected and working |
| GET | `/admin/accounts/expense/create` | AccountsController | expenseCreate | AuthMiddleware | Protected and working |
| GET | `/admin/accounts/statement` | AccountsController | statement | AuthMiddleware | Protected and working |
| GET | `/admin/fees/collect` | FeesController | collect | AuthMiddleware | Protected and working |
| GET | `/admin/fees/receipt` | FeesController | receipt | AuthMiddleware | Protected and working |
| GET | `/admin/fees/defaulters` | FeesController | defaulters | AuthMiddleware | Protected and working |
| GET | `/admin/salary/pay` | SalaryController | pay | AuthMiddleware | Protected and working |
| GET | `/admin/salary/receipt` | SalaryController | receipt | AuthMiddleware | Protected and working |
| GET | `/admin/attendance/students` | AttendanceController | students | AuthMiddleware | Protected and working |
| GET | `/admin/attendance/employees` | AttendanceController | employees | AuthMiddleware | Protected and working |
| GET | `/admin/attendance/classwise` | AttendanceController | classwise | AuthMiddleware | Protected and working |
| GET | `/admin/attendance/student-report` | AttendanceController | studentReport | AuthMiddleware | Protected and working |
| GET | `/admin/attendance/employee-report` | AttendanceController | employeeReport | AuthMiddleware | Protected and working |
| GET | `/admin/homework` | HomeworkController | index | AuthMiddleware | Protected and working |
| GET | `/admin/communication/messaging` | CommunicationController | messaging | AuthMiddleware | Protected and working |
| GET | `/admin/communication/sms-gateway` | CommunicationController | smsGateway | AuthMiddleware | Protected and working |
| GET | `/admin/communication/live-class` | CommunicationController | liveClass | AuthMiddleware | Protected and working |
| GET | `/admin/exams/create` | ExamsController | create | AuthMiddleware | Protected and working |
| GET | `/admin/exams/setup` | ExamsController | setup | AuthMiddleware | Protected and working |
| GET | `/admin/exams/marks` | ExamsController | marks | AuthMiddleware | Protected and working |
| GET | `/admin/exams/results` | ExamsController | results | AuthMiddleware | Protected and working |
| GET | `/admin/class-tests/marks` | ClassTestsController | marks | AuthMiddleware | Protected and working |
| GET | `/admin/class-tests/results` | ClassTestsController | results | AuthMiddleware | Protected and working |
| GET | `/admin/reports/admissions` | ReportsController | admissions | AuthMiddleware | Protected and working |
| GET | `/admin/reports/contacts` | ReportsController | contacts | AuthMiddleware | Protected and working |
| GET | `/admin/certificates` | CertificatesController | index | AuthMiddleware | Protected and working |

| GET | `/login` | AuthController | create | GuestMiddleware | Working |
| POST | `/login` | AuthController | store | GuestMiddleware, VerifyCsrfToken | Working |
| POST | `/logout` | AuthController | destroy | VerifyCsrfToken | Working |

Unknown routes render the shared maroon 404 page. State-changing routes exist only for the completed authentication, settings, classes, and subjects modules.

## Fast-track mutation routes

All routes below use authentication, the admin-role guard, and CSRF validation:

- `POST /admin/students/logins`, `POST /admin/students/promotions`
- `POST /admin/employees/logins`
- `POST /admin/salary/pay`, with `GET /admin/salary/receipt/{id}`
- `POST /admin/homework`
- `POST /admin/homework/{id}`
- `POST /admin/exams`, `POST /admin/exams/setup`, `POST /admin/exams/marks`
- `POST /admin/exams/{id}/delete`
- `POST /admin/class-tests/marks`, `POST /admin/certificates`
- `POST /admin/certificates/{id}/delete`
- `POST /admin/communication/messaging`, `POST /admin/communication/messaging/{id}`
- `POST /admin/communication/sms-gateway`, `POST /admin/communication/live-class`

## General settings completion routes

- `GET|POST /admin/settings/fee-structure`
- `GET|POST /admin/settings/discount-types`
- `POST /admin/settings/discount-types/{id}/status`
- `GET|POST /admin/settings/theme-language`

All General Settings mutations require authentication, the admin-role guard, CSRF validation, server-side value validation, and institute ownership.

## Priority module routes

Student management adds `POST /admin/students`, `GET /admin/students/{id}`, `GET /admin/students/{id}/edit`, `POST /admin/students/{id}`, and `POST /admin/students/{id}/status`.

Employee management adds `POST /admin/employees`, `GET /admin/employees/{id}/edit`, `POST /admin/employees/{id}`, and `POST /admin/employees/{id}/status`.

Attendance adds `POST /admin/attendance/students` and `POST /admin/attendance/employees`. Finance adds `POST /admin/accounts/{type}`. Fees add `POST /admin/fees/invoice`, `POST /admin/fees/payment`, and `GET /admin/fees/receipt/{id}`. Every priority mutation uses `AuthMiddleware`, `AdminRoleMiddleware`, and `VerifyCsrfToken`.
# PDF report routes

All PDF routes are authenticated admin routes under `/admin/reports`. They cover admission letters, job letters, salary slips/sheets, fee receipts/invoices, report cards, certificates, attendance exports, student directories, admission/contact reports, and account statements. Inline preview is the default; `?download=1` downloads the file. Template administration is available at `/admin/settings/report-templates` with save, reset, and preview endpoints.

## Student and employee portals

Registered in `routes/portal.php` (loaded by `bootstrap/app.php`). The prefix is guarded by
`AuthMiddleware` then `PortalMiddleware`; it deliberately sits **outside** `/admin`, because
`routes/web.php` applies `AdminRoleMiddleware` to that whole prefix and would reject every
portal user. All portal routes are read-only GETs.

| Method | Path | Controller action | Notes |
| --- | --- | --- | --- |
| GET | /portal/student | StudentPortalController::dashboard | Attendance %, fees outstanding, latest result |
| GET | /portal/student/profile | StudentPortalController::profile | Admission and guardian record |
| GET | /portal/student/attendance | StudentPortalController::attendance | `from`/`to` range, validated server-side |
| GET | /portal/student/fees | StudentPortalController::fees | Own invoices and payments |
| GET | /portal/student/fees/{id} | StudentPortalController::invoice | 404 for an invoice owned by another student |
| GET | /portal/student/results | StudentPortalController::results | Exams (graded), class tests, certificates |
| GET | /portal/student/homework | StudentPortalController::homework | Homework for the student's class |
| GET | /portal/employee | EmployeePortalController::dashboard | Attendance %, salary paid, class count |
| GET | /portal/employee/profile | EmployeePortalController::profile | Employment and personal record |
| GET | /portal/employee/attendance | EmployeePortalController::attendance | `from`/`to` range, validated server-side |
| GET | /portal/employee/salary | EmployeePortalController::salary | Own salary payment history |
| GET | /portal/employee/classes | EmployeePortalController::classes | Matched on `classes.teacher_reference` |
| GET | /portal/employee/homework | EmployeePortalController::homework | Homework this employee assigned |

Login now routes by role (`AuthController::landingRoute`): admin to `/admin/dashboard`,
student to `/portal/student`, employee to `/portal/employee`. `GuestMiddleware` uses the same
mapping, so a signed-in portal user visiting `/login` is no longer bounced into a 403.

### Portal parity additions (7 September 2026)

Added after auditing eSkooly's `/bb/StudentPortal/` page inventory — see
`docs/ESKOOLY-PORTAL-PARITY.md`.

| Method | Path | Controller action | Notes |
| --- | --- | --- | --- |
| GET | /portal/student/documents | StudentPortalController::documents | Lists documents the student can generate |
| GET | /portal/student/documents/admission-letter | PortalPdfController::admissionLetter | Branded PDF |
| GET | /portal/student/documents/fee-slip/{payment} | PortalPdfController::feeSlip | 404 unless the payment is the student's |
| GET | /portal/student/documents/report-card/{exam} | PortalPdfController::reportCard | 404 unless the student sat that exam |
| GET | /portal/employee/documents | EmployeePortalController::documents | Lists documents the employee can generate |
| GET | /portal/employee/documents/job-letter | PortalPdfController::jobLetter | Branded PDF |
| GET | /portal/employee/documents/salary-slip/{payment} | PortalPdfController::salarySlip | 404 unless the payment is the employee's |
| GET | /portal/messages | PortalMessagesController::index | Inbox plus new-conversation form |
| GET | /portal/messages/{id} | PortalMessagesController::show | 404 unless a participant |
| POST | /portal/messages | PortalMessagesController::start | CSRF |
| POST | /portal/messages/{id} | PortalMessagesController::reply | CSRF |
| GET | /portal/account | PortalAccountController::edit | Account settings |
| POST | /portal/account | PortalAccountController::update | CSRF; requires current password |

`/portal/messages` and `/portal/account` serve both audiences; the controller resolves the
audience via `PortalController::identity()`.
