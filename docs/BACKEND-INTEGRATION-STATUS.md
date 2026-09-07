# Backend Integration Status

Updated: 6 September 2026

Public signup availability: DEFERRED until launch at the owner's request. /signup displays “We'll be live soon”; direct registration submissions are blocked server-side and cannot create accounts. Existing account login remains available. The registration implementation and tests below are retained for future activation.

Premium public login and institute signup: COMPLETE. Shared responsive maroon shell, original local school-workspace SVG, accessible password toggles and field errors. Signup persists the institute, owner, safe server-assigned admin role, membership and preferences atomically. Migration 000008 associates existing users with their existing institute and scopes shared settings reads by membership. Signup tests cover validation, CSRF, hashing, role injection, session regeneration, uniqueness rollback and cross-institute student/message isolation.

| Page / workflow | GET view | Mutations | Database | Validation / authorization | Status |
|---|---|---|---|---|---|
| Authentication and RBAC | Bound | Login/logout | Users, roles, permissions | CSRF, session rotation, active account | COMPLETE |
| Account and institute settings | Bound | Bound | Settings tables | Validation, safe uploads, permissions | COMPLETE |
| Fee structure | Bound by class | Transactional bulk update | classes.monthly_tuition | Class ownership, non-negative values, CSRF | COMPLETE |
| Discount types | Bound with status list | Create/update-by-name/status | discount_types | Mode/value limits, institute ownership, CSRF | COMPLETE |
| Theme and language | Bound | Save preferences | institute_preferences | Allowed-value validation, CSRF | COMPLETE |
| Classes and subjects | Bound | Bound | Academic tables | Ownership and duplicate validation | COMPLETE |
| Student master records | Bound | Create/edit/status | Students | Admin, CSRF, validation, uploads | COMPLETE |
| Admission letter | Bound to student/institute/rules | Print only | Existing records | Admin selection | COMPLETE |
| Student ID cards | Bound with filters/photo | Print only | Existing records | Bounded institute query | COMPLETE |
| Print basic list | Bound with search/class/pagination | Print only | Students, invoices | Bounded institute query | COMPLETE |
| Student credentials | Bound | Secure account creation | Users, student_accounts | Hashing, one-time temporary password, CSRF | COMPLETE |
| Student promotion | Bound with selection/history | Transactional promotion | Students, student_promotions | Class ownership, rollback, CSRF | COMPLETE |
| Student report card | Bound | Read/print | Exams and marks | Configured grading | COMPLETE |
| Employee master records | Bound | Create/edit/status | Employees | Admin, CSRF, validation, uploads | COMPLETE |
| Employee job letter | Bound to employee/institute/rules | Print only | Existing records | Admin selection | COMPLETE |
| Staff credentials | Bound | Secure account creation | Users, employee_accounts | Hashing; designation is not an RBAC role | COMPLETE |
| Salary payment and slip | Bound | Persist payment | salary_payments | Amount/date validation, unique employee/month, CSRF | COMPLETE |
| Attendance entry and reports | Bound | Transactional upsert | Attendance tables | Date/class ownership and status validation | COMPLETE |
| Income, expense and account statement | Bound | Persist transactions | Account ledger | Positive amount/date, admin, CSRF | COMPLETE |
| Fee invoices, payments and receipts | Bound | Transactional invoice/payment | Fee tables | Server calculation, carry-forward, CSRF | COMPLETE |
| Homework | Bound with filters/add/update/attachments | Create/update | homework | Teacher/class/subject and upload validation | COMPLETE |
| Exam creation and setup | Bound | Create/delete/setup | exams, exam_subjects | Date, ownership, subject mapping, CSRF | COMPLETE |
| Exam marks and results | Bound | Bulk upsert | exam_marks | Transaction, membership, bounds, configured grades | COMPLETE |
| Class tests and reports | Bound with class/subject/student/date/performance filters | Create and save marks | class_tests, class_test_marks | Membership, bounds, CSRF | COMPLETE |
| Admission and contact reports | Bound with filters/pagination | Read/print | Student/guardian records | Bounded institute query, server-side age | COMPLETE |
| Certificates | Bound with recipient selectors/list | Create/delete | certificates | Exactly one owned recipient, CSRF | COMPLETE |
| Messaging | Bound with chats/history/unread state | Create/send | Conversation tables | Participant and institute isolation, CSRF | COMPLETE |
| SMS gateway foundation | Bound | Save settings | sms_settings | Encrypted secrets and masked reads | COMPLETE |
| Live Class | Existing page retained | Existing scheduling foundation retained | live_classes | Deferred at user request | DEFERRED |
| Deployment and backup guidance | Documentation | N/A | Migration procedure | Secrets, sessions, uploads, restore drills | COMPLETE |

All state-changing admin routes use authentication, the admin-role middleware, and CSRF validation. Views contain no SQL and the completed workflows no longer depend on static/demo business rows.
# Premium PDF report engine

Implemented: centralized mPDF rendering, tenant-scoped editable templates, safe placeholders and HTML, deterministic filenames, inline/download responses, fourteen report families, migration, automated tests, and a generated QA fixture.

## Student and Employee Portals — COMPLETE (7 September 2026)

Previously both were blocked at login with "Student and employee portals are undergoing
testing and will be live soon." That restriction is removed; both portals are live.

| Area | Status |
| --- | --- |
| Student portal (dashboard, profile, attendance, fees, invoice detail, results, homework) | COMPLETE |
| Employee portal (dashboard, profile, attendance, salary, classes, homework) | COMPLETE |
| Role grant on managed login creation | COMPLETE — `CredentialService::grant()` |
| Backfill for pre-existing logins | COMPLETE — migration 000015 (38 student accounts) |
| Role-aware login and guest redirects | COMPLETE |
| Automated tests | COMPLETE — `tests/Feature/PortalFeatureTest.php`, 39 checks |

Files added: `app/Controllers/{PortalController,StudentPortalController,EmployeePortalController}.php`,
`app/Repositories/PortalRepository.php`, `app/Middleware/PortalMiddleware.php`,
`app/Views/layouts/portal.php`, `app/Views/partials/portal-header.php`,
`app/Views/portal/{student,employee}/*.php`, `routes/portal.php`,
`database/migrations/2026_09_07_000015_create_portal_roles_and_memberships.php`.

Files changed: `app/Services/AuthService.php` (accept portal roles),
`app/Services/CredentialService.php` (grant role + membership),
`app/Controllers/AuthController.php` (role-aware landing),
`app/Middleware/GuestMiddleware.php` (role-aware redirect), `bootstrap/app.php`, `tests/run.php`.

Portals are **read-only by design** — no route mutates data, so none carries a state-changing
form beyond the shared logout. The UI reuses the existing maroon shell (`components.css`,
`admin-pages.css`, shared sidebar partial); no new design system was introduced.

### Known limitation

`PortalRepository::employeeClasses()` matches on `classes.teacher_reference`, which is still a
free-text `VARCHAR(120)` rather than a foreign key to `employees`. An employee whose stored
class teacher string differs from their `employees.name` will see an empty "My Classes" list.
Converting that column to a real FK is tracked in `docs/CURRENT-IMPLEMENTATION-AUDIT.md`.

### Portal parity pass (7 September 2026)

Audited our portals against eSkooly's actual `/bb/StudentPortal/` page inventory
(`docs/ESKOOLY-PORTAL-PARITY.md`). Five gaps were found and closed:

| Gap vs eSkooly | Closed by |
| --- | --- |
| `account.php` — self-service account settings | `PortalAccountController` + `/portal/account` |
| `MessageBox.php` — internal messaging | `PortalMessagesController` + `/portal/messages` |
| `admissionletter.php` — admission letter | `PortalPdfController::admissionLetter` |
| `feeslip.php` — fees paid slip | `PortalPdfController::feeSlip` |
| `reportcard.php` — result card | `PortalPdfController::reportCard` |

Employee equivalents (job letter, salary slip, messaging, account settings) were added by
analogy — eSkooly has no employee portal, since `EMP` logins enter the permission-filtered
admin app and Roles & Permissions is Desktop-subscription-only.

Every non-excluded eSkooly student-portal page now has an equivalent; `liveclass.php` stays
EXCLUDED per project scope. Suite: 206 checks passing.

Files added: `app/Controllers/{PortalPdfController,PortalAccountController,PortalMessagesController}.php`,
`app/Views/portal/{account,messages}.php`, `app/Views/portal/{student,employee}/documents.php`.
# 2026-09-07 Pakistan ERP foundation update

Added institute-scoped guardian accounts and parent ownership, normalized teacher assignments, class notes, four-state attendance, assessment absence/publication fields, academic sessions/rule profiles, fee automation/reversal/void metadata, WhatsApp templates/logging, and audit logs. See `MUST-HAVE-FEATURES-STATUS.md` for the honest completion matrix.
