# Current Implementation Audit

Updated: 7 September 2026

Purpose: a verified, code-derived inventory of what this application actually implements, produced before any eSkooly parity work. This document supersedes status claims in older docs where they conflict; see [Stale documentation corrections](#stale-documentation-corrections).

Method: direct inspection of `routes/web.php`, `routes/auth.php`, `app/Controllers`, `app/Services`, `app/Repositories`, `app/Views`, `app/Middleware`, `config/navigation.php`, `config/pages.php`, `database/migrations`, and `tests/`. No claim below is carried over from a previous document without re-verification.

Status vocabulary: `COMPLETE` | `PARTIAL` | `SCAFFOLD-ONLY` | `MISSING` | `EXCLUDED`.

---

## 1. Architecture baseline

| Aspect | Finding |
|---|---|
| Stack | Framework-free Core PHP 8.2+. Only runtime dependency is `mpdf/mpdf ^8.3`. No dev dependencies. |
| Entry | `public/index.php` → `bootstrap/app.php` → `App::run(Request)`. `public/router.php` is the built-in-server shim. |
| Router | `app/Core/Router.php`. Flat route array, **`get()`/`post()` only** — no PUT/DELETE; deletes are `POST /…/delete`. 140 routes total (135 web + 5 auth). |
| Views | `app/Core/View.php` — `extract()` + output buffering, no compilation. Layouts in `app/Views/layouts/`. |
| Layering | Controller → Service → Repository, applied **inconsistently**. Only 10 services exist; most controllers instantiate repositories inline (e.g. `AccountsController::r()`). `app/Models/` exists and is empty. No DI container. |
| Code style | Most controller/service/repository files are **minified to one line per method**. Edits must match surrounding style; wholesale reformatting is out of scope. |
| Page metadata | `config/pages.php` — 57 page keys plus a `_demo` block, consumed by `AdminPageController::renderPage()`. |

## 2. Module inventory

### Authentication & session — `COMPLETE`

- Routes: `routes/auth.php` (5) — `GET/POST /login`, `GET/POST /signup`, `POST /logout`.
- Controller: `AuthController` (`create`, `store`, `destroy`, `signup`, `register`). Service: `AuthService` (`attempt`, `logout`, `register`).
- Tables: `users`, `roles`, `permissions`, `user_roles`, `role_permissions` (migration `000001`), `institute_users` (`000008`, `000010`).
- Tests: `tests/Feature/AuthFeatureTest.php`, `tests/Feature/SignupFeatureTest.php`.
- Note: public signup is intentionally blocked server-side (403) despite the routes existing; the internal registration service still works.

### Roles & permissions (RBAC) — `SCAFFOLD-ONLY` ← **largest gap**

- Tables exist and are correct (`000001`). 12 permission slugs seeded in `database/seed.php:29`: `dashboard.view`, `settings.view`, `students.view`, `employees.view`, `accounts.view`, `fees.view`, `salary.view`, `attendance.view`, `homework.view`, `exams.view`, `reports.view`, `communication.view`.
- **Only `settings.view` is ever checked** — in `SettingsController::denied()` and `ReportTemplateController::allowed()`. The other **ten slugs are seeded but never referenced anywhere in application code**.
- Every other module uses an ad-hoc private `denied()` returning `hasRole('admin') ? null : 403` (`AccountsController`, `AttendanceController`, `ClassesController`, `EmployeesController`, `FeesController`, `SubjectsController`).
- `app/Middleware/RoleMiddleware.php` implements a real `can()` check but **is not referenced by any route** — dead code.
- Global guards at `routes/web.php:176-177` apply `AuthMiddleware` + `AdminRoleMiddleware` to the whole `/admin` prefix, which makes per-route `AdminRoleMiddleware` entries redundant and means **the app is effectively admin-only**. Non-admin roles cannot use the product.
- **No Roles & Permissions UI at all** — no route, controller or view.
- Tests: middleware allow/deny and role helpers covered in `AuthFeatureTest`; no privilege-escalation test.

### General settings — `COMPLETE`

- 24 routes under `/admin/settings/*`. `SettingsController` (20 actions) + `ReportTemplateController` (4). Service `SettingsService`, repository `SettingsRepository` (18 methods).
- Covers: Account Settings, Institute Profile, Fee Particulars, Fee Invoice Accounts, Rules & Regulations, Marks Grading, Fee Structure, Discount Types, Theme & Language, Report Templates.
- Tables: `institutes`, `institute_preferences`, `fee_particulars`, `fee_invoice_accounts`, `rules`, `grading_rules`, `discount_types`, `report_templates`.
- Tests: `SettingsAcademicsFeatureTest`, `GeneralSettingsCompletionTest`.
- Gap: `report_templates` persistence has **no test coverage** (see corrections).

### Classes — `COMPLETE`

- 12 routes shared with Subjects. `ClassesController`: full CRUD plus `bulkDelete`, `forceDelete`, `forceBulkDelete` with in-use protection.
- Tables: `classes`, `class_subjects`. Repository: `AcademicRepository`.
- Gap: `classes.teacher_reference` is an unconstrained `VARCHAR(120)`, **not** an FK to `employees`.

### Subjects — `PARTIAL`

- `SubjectsController`: `index`, `assign`, `store` only. **No update, no delete route.**

### Students — `COMPLETE`

21 routes; `StudentsController` has 21 actions — the most complete module in the app.

| Workflow | Implementation |
|---|---|
| All Students / Add / Edit / Show / Delete / Bulk delete | `index`, `create`, `store`, `show`, `edit`, `update`, `delete`, `bulkDelete` |
| Manage Families | `families` / `saveFamilies` → `StudentRepository::updateFamilies()`. **Label-based**: writes the free-text `students.family VARCHAR(120)` column (migration `000004`, alongside `total_siblings`). There is no families/guardians entity; guardian data is denormalized as `father_*` / `mother_*` columns. |
| Active / Inactive | `statusManager`, `bulkStatus`, `status` |
| Manage Login | `logins` / `createLogin` → `CredentialService` (`create`, `reveal`), AES-256-GCM `password_cipher` (migrations `000012`, `000013`) |
| Promotions | `promotions` / `promote` → `student_promotions` |
| Admission Letter / ID Cards / Basic List / Report Card | `admissionLetter`, `idCards`, `basicList`, `reportCard` + PDF endpoints |
| Documents | `StudentService::storeDocument` → `student_documents` (migration `000009`) |
| Fee overrides | `students.monthly_fee_override`, `discount_type`, `discount_value` (migration `000011`) |

Tests: `PriorityModulesFeatureTest`, `RemainingWorkflowsFeatureTest`, `BoundViewsFeatureTest`.

**Consequence for planning:** the master prompt's "Phase A — Core Student Administration Gaps" is substantially already delivered. Do not rebuild it.

### Employees — `PARTIAL`

- 9 routes. `EmployeesController`: `index`, `create`, `edit`, `store`, `update`, `status`, `jobLetter`, `logins`, `createLogin`.
- **No delete route.** **No Staff ID Cards workflow** (students have one; employees do not).
- `jobLetter`, `logins` and `createLogin` omit the controller-level `denied()` check. Not currently exploitable — the global `/admin` `AdminRoleMiddleware` covers them — but it is inconsistent defence-in-depth and will become a real hole the moment non-admin roles are enabled.
- `employees.role` is an ENUM of job designations (Principal / Management Staff / Teacher / Accountant / Store Manager / Other) and is **not** an application RBAC role. These must stay distinct.

### Accounts — `PARTIAL`

- 7 routes. Account heads create/read/delete, transaction create, statement with running balance.
- **No transaction update or delete.** Tables: `account_heads`, `account_transactions` (polymorphic `reference_type`/`reference_id`, unconstrained).

### Fees — `PARTIAL`

- 9 routes. `FeesController`: `collect`, `createInvoice`, `payment`, `receipt`, `defaulters`, `report`, `deleteIndex`, `deleteInvoice`. Service `FeeService`, repository `FeeRepository` (17 methods).
- Tables: `fee_invoices` (status ENUM includes `carried_forward` from `000005`), `fee_invoice_items`, `fee_payments`.
- Gaps: **no Family Wise Fees workflow**; no invoice update; `app/Views/fees/delete.php` is a one-line `require` of `fees/report.php`; `config/navigation.php` points both "Generate Fees Invoice" and "Collect Fees" at `/admin/fees/collect`.

### Salary — `PARTIAL`

- 4 routes: `pay`, `store`, `receipt`. Table `salary_payments` with `UNIQUE(employee_id, salary_month)`.
- PDF exists for salary slip **and** salary sheet, but there is **no Salary Report screen**.

### Attendance — `PARTIAL`

- 7 routes: student/employee mark + save, `classwise`, `studentReport`, `employeeReport`. Service `AttendanceService`, repository `AttendanceRepository` (9 methods). Upsert semantics enforced by `UNIQUE(student_id, attendance_date)`.
- **`app/Views/attendance/classwise.php` is a hardcoded static view** — the controller action passes real data (`classSummary`) but the view does not render it. This is a live demo-data defect, not merely a missing feature.
- Card-scanning attendance: `MISSING` (hardware-dependent; do not fake).

### Homework — `PARTIAL`

- 3 routes: `index`, `store`, `update`. **No delete.** Table `homework` — **missing `institute_id`** although `class_tests` has one; tenancy is inferred by join.

### Exams — `PARTIAL`

- 8 routes: `create`, `store`, `delete`, `setup`, `saveSetup`, `marks`, `saveMarks`, `results`. **No exam edit.**
- Tables: `exams`, `exam_subjects`, `exam_marks`. Grading via `grading_rules`.

### Class tests — `PARTIAL`

- 3 routes: `marks`, `store`, `results`. **No test create/edit/delete UI**; `class_tests` rows have no management screen.

### Reports (HTML) — `PARTIAL`

- `ReportsController` exposes only `admissions` and `contacts`. Financial, academic and attendance report screens are absent as HTML pages (some exist only as PDF endpoints).

### Certificates — `PARTIAL`

- 3 routes: `index`, `store`, `delete`. **No edit.** Table `certificates` supports student and employee recipients.

### Messaging (internal) — `COMPLETE`

- `CommunicationController`: `messaging`, `startConversation`, `sendMessage`. Tables `conversations`, `conversation_participants`, `messages`. Participant isolation asserted in `RemainingWorkflowsFeatureTest`.

### PDF / reporting — `COMPLETE`

- mPDF 8.3. `app/Services/Pdf/`: `PdfService::render`, `ReportFilenameService::make`, `ReportTemplateService` (defaults, validate, placeholders, renderText, sanitizeHtml).
- **14 report families**, all `GET /admin/reports/…/pdf`: admission-letter, job-letter, salary-slip, salary-sheet, fee-receipt, fee-invoice, report-card, certificate, student-attendance, employee-attendance, student-basic-list, admissions, contacts, account-statement.
- Templates: only `app/Views/pdf/document.php` (generic renderer used by 13 of 14) and `certificate.php`, plus `pdf/partials/{header,footer}.php`. Variation comes from DB template rows + data arrays.
- Branding defaults: `primary_color #7A1632`, `accent_color #D9A441`, toggles for logo/address/phone/website/generated date/page numbers.
- Tests: `PdfReportingFeatureTest` (filename safety, sanitization, invalid colors, binary output, inline headers).

### Modules absent entirely — `MISSING`

| Module | Evidence |
|---|---|
| **Timetable** | No table, migration, route, controller, view or navigation entry. |
| **Behaviour & Skills** | No table, route or view. |
| **Question Paper** | No table, route or view. |
| **Staff ID Cards** | Students have ID cards; employees do not. |
| **Family Wise Fees** | No route or repository method. |
| **Salary Report** | Salary sheet PDF exists; no report screen. |

### Excluded this pass — `EXCLUDED`

Per §5/§44 of the parity brief: **POS**, **Online Store / Live Store**, **Live Classes**, **SMS Gateway**.

Note that Live Classes and SMS are *partially built* here: `live_classes` and `sms_settings` tables, `CommunicationController::{smsGateway, saveSms, liveClass, storeLiveClass}`, `SmsSettingsService`, and `SmsProviderInterface` (**interface only — no implementation**). Views `communication/live-class.php` and `communication/sms-gateway.php` are hardcoded static pages. These stay as-is; no further work this pass.

## 3. Data layer

33 tables plus a `migrations` ledger, across 14 migrations. `database/migrate.php` runs un-applied files in filename order.

- Migrations `000001`–`000004` are driver-aware (SQLite + MySQL). **`000005` onward are MySQL-only** (`SHOW COLUMNS`, `information_schema`, `MODIFY`, `ENUM`, `JSON`), so the SQLite branches in the first four are effectively dead code. New migrations follow the MySQL-only convention.
- `institute_id` present on 20 tables. **Tenancy is join-inferred** on ~20 others including `fee_invoices`, `fee_payments`, `salary_payments`, `student_attendance`, `employee_attendance`, `exam_subjects`, `exam_marks`, `homework`, `messages`. `homework` lacking `institute_id` while `class_tests` has it is a genuine inconsistency.
- `institute_users.user_id` is the **primary key**, so a user belongs to exactly one institute.
- `database/seeds/` is empty (`.gitkeep` only). Seeding lives in `database/seed.php` (admin bootstrap) and `database/seed_client_preview.php` (demo data, requires `--seed`).

## 4. Tests

- **No PHPUnit.** No `phpunit.xml`; `composer.json` has **no `scripts` block**. The `autoload-dev` PSR-4 `Tests\` mapping is unused — no test file declares that namespace.
- Runner: `php tests/run.php`. Each `tests/Feature/*.php` returns a `Closure(App): array` of named booleans; the runner prints `[PASS]`/`[FAIL]` and exits non-zero on any failure.
- These are **integration tests**: they require live MySQL on `127.0.0.1:3307` and `DEV_ADMIN_EMAIL` / `DEV_ADMIN_PASSWORD` in `.env`.
- 9 files: `run.php` (helpers, CSRF, validator, routing, dashboard), `AuthFeatureTest`, `SettingsAcademicsFeatureTest`, `PriorityModulesFeatureTest`, `RemainingWorkflowsFeatureTest`, `BoundViewsFeatureTest`, `GeneralSettingsCompletionTest`, `SignupFeatureTest`, `PdfReportingFeatureTest`.
- Coverage gaps: no privilege-escalation test, no `report_templates` persistence test, `tests/Unit/` is empty.

## 5. Stale documentation corrections

Verified against code on 7 September 2026. These must be fixed in the source documents.

| # | Document | Claim | Reality |
|---|---|---|---|
| 1 | `PRIORITY-DELIVERY-STATUS.md:10` | "Migrations: current through `000007`" | `000008`–`000014` exist (`institute_users`, `student_documents`, fee overrides, credential ciphers, `report_templates`). |
| 2 | `PRIORITY-DELIVERY-STATUS.md` | "Complete" list | Omits student documents, per-student fee overrides, managed credential ciphers, and the entire PDF engine + `report_templates`. Deferred list never mentions the deferred public signup. |
| 3 | `ROUTES.md:80` | "State-changing routes exist only for authentication, settings, classes, and subjects" | Contradicted by the fast-track / general-settings / priority-module sections **later in the same file** and by `routes/web.php` (135 routes). Leftover from an earlier phase. |
| 4 | `ROUTES.md` | route table | No rows for `student_documents` upload/delete. |
| 5 | `DATABASE-SCHEMA.md` | schema walkthrough | Never documents `student_documents` (`000009`), the `students` fee-override columns (`000011`), or `password_cipher` (`000012`). |
| 6 | `DATABASE-SCHEMA.md:25` | `classes.teacher_reference` "remains nullable until the Employees phase" | That phase shipped; the column is still an unconstrained `VARCHAR(120)` with no FK to `employees`. |
| 7 | `BACKEND-INTEGRATION-STATUS.md:39` | Live Class `DEFERRED` | `live_classes` is migrated, routed and test-asserted. The label likely means "no external video provider" but reads as unimplemented. (Module is EXCLUDED this pass regardless.) |
| 8 | `BACKEND-INTEGRATION-STATUS.md:45` | report templates have "automated tests" | **Nothing in the suite reads or writes `report_templates`**, and `/admin/settings/report-templates` is absent from `BoundViewsFeatureTest`'s 21-path list. |
| 9 | `config/pages.php` header | "No database is queried in this scaffold" | Untrue — controllers query throughout. |
| 10 | Six view files | carry the "Approved static page content" marker | `settings/account.php`, `institute-profile.php`, `fee-particulars.php`, `marks-grading.php`, `rules-regulations.php`, `fee-invoice-accounts.php` are all data-bound. Markers are misleading. |
| 11 | `README.md:42` | `tests/` are "lightweight foundation checks" | It is a MySQL-dependent integration suite; `php tests/run.php` is the only entry point and is not discoverable from Composer. |

Genuinely static views (marker is accurate): `attendance/classwise.php`, `communication/live-class.php`, `communication/sms-gateway.php`.

## 6. Summary

| Status | Modules |
|---|---|
| `COMPLETE` | Authentication, General Settings, Classes, Students, Messaging, PDF/Reporting |
| `PARTIAL` | Subjects, Employees, Accounts, Fees, Salary, Attendance, Homework, Exams, Class Tests, Reports, Certificates |
| `SCAFFOLD-ONLY` | Roles & Permissions |
| `MISSING` | Timetable, Behaviour & Skills, Question Paper, Staff ID Cards, Family Wise Fees, Salary Report |
| `EXCLUDED` | POS, Online Store, Live Classes, SMS Gateway |

Highest-value gap: **Roles & Permissions**. The tables, helpers and middleware all exist; only wiring and a UI are missing, and until that lands the product is single-role.

Next step is blocked on eSkooly discovery — see `docs/ESKOOLY-RESEARCH-SOURCES.md`.
