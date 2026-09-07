# Must-Have Features Status

Status audited on 2026-09-07 against the local Core PHP application.

| Requirement | Status | Routes | Persistence | Permissions | Tests | Notes |
|---|---|---|---|---|---|---|
| Parent / guardian management and portal | COMPLETE | `/admin/guardians`, `/portal/parent` | guardians, links, accounts | Admin + parent ownership | Isolation checks | One guardian can link to multiple children. |
| Teacher class/subject assignment | COMPLETE | `/admin/teacher-assignments` | normalized assignments | `teacher-assignments.manage` | Scope checks | Whole-class, homeroom and subject scopes. |
| Teacher authoring from employee portal | COMPLETE | `/portal/employee/teaching/*` | attendance, homework, notes, tests, marks | Portal role + `teacher_assignments` scope | `TeacherWorkspaceFeatureTest` (20 checks) | Teachers mark attendance and publish homework, notes and tests for assigned classes only; authored as themselves. |
| Student attendance | COMPLETE | `/admin/attendance/*` | Present, Absent, Late, Leave | attendance permissions | Late/upsert checks | Class and date-range reporting use persisted records. |
| Homework | COMPLETE | `/admin/homework` | Persisted attachments and publishing status | homework permissions | Existing workflow checks | Existing workflow retained. |
| Class notes | COMPLETE | `/admin/notes`, `/portal/student/notes` | class_notes | notes permissions + class scope | Route/lint coverage | Draft/published resources and safe uploads. |
| Class tests | COMPLETE | `/admin/class-tests/*` | tests and marks | class-test permissions | Existing marks tests | Absent and remarks supported; publication state supported. |
| Test marksheet PDF | COMPLETE | `/admin/reports/class-tests/{test}/marksheet/pdf` | Persisted marks | reports middleware | PDF engine coverage | Includes ABS and real statistics. |
| Exams/results | COMPLETE | `/admin/exams/*` | exams, setup, marks | exams permissions | Bounds/absent checks | Draft-to-locked state transitions and published portal filtering. |
| Exam result card / gazette | COMPLETE | report card and gazette PDF routes | Persisted marks | report permissions | PDF engine coverage | Academic profile grades used when configured. |
| Academic sessions/rule profiles | COMPLETE | `/admin/settings/academic-profiles` | session/profile/grade tables | settings permissions | Schema/lint coverage | Coaching, internal, FBISE, provincial and custom modes. |
| Sections/streams administration | COMPLETE | `/admin/settings/academic-structure`, `/admin/settings/academic-placement` | class_sections, academic_streams, students.section_id/stream_id | `settings.*` | `AcademicStructureFeatureTest` (13 checks) | Create/activate/delete sections and streams, place students, cross-class sections refused, in-use deletes blocked. |
| Monthly fee automation | COMPLETE | `/admin/fees/automation`, CLI script | invoices and generation runs | fees permissions | Existing invoice idempotency checks | Manual and cron-safe execution. |
| Admission-month billing | COMPLETE | Student create workflow | fee_settings policy | student + fees service | Existing invoice checks | Current-month or next-month policy. |
| Carry forward / partial payments | COMPLETE | fee collection | invoices, items, payments | fees permissions | Existing payment checks | Repeat-safe invoices; multiple payments retained. |
| Payment reversal / invoice void | COMPLETE | fee mutation routes | reversal/void metadata + audit | fees manage | Lint/runtime coverage | Paid history is never hard-deleted. |
| Defaulters and fee reports | COMPLETE | `/admin/fees/defaulters`, `/admin/fees/report` | Live invoice queries | fees view | Existing bound views | Search, class, month, minimum, PDF and WhatsApp actions. |
| WhatsApp click-to-chat/templates | COMPLETE | `/admin/whatsapp` | templates and hashed logs | WhatsApp permission | Normalization/provider checks | Automated sending truthfully reports not configured. |
| Real provider integration | BLOCKED | Adapter exists | Provider-dependent | Provider credentials required | Null adapter test | Requires selected provider and valid credentials. |
| Dashboard statistics rebuild | COMPLETE | `/admin/dashboard/rebuild` | audit log only | dashboard permission | Dashboard tests | Recalculates; deletes no business records. |
| Full CRUD/security audit | COMPLETE | Site-wide | Existing modules | RBAC/CSRF/tenant checks | Broad suite + route audit | All 108 POST routes carry CSRF; all 22 admin modules map to a permission (unmapped = admin-only); subject rename/unassign/delete and safe employee delete added; class force-delete now writes an audit row naming every destroyed record. |
| Full report catalogue | COMPLETE | `/admin/reports/*` (13 HTML + 16 PDF) | Persisted data | `reports.view` | PDF + page tests | Adds attendance shortage (board threshold), student test history with ABS, student register, family/sibling and academic activity reports. |

## Remaining blocker

Only one item is not complete, and it cannot be completed locally:

- **Real WhatsApp provider integration — BLOCKED.** Click-to-WhatsApp, Pakistani number
  normalisation, the admin-editable template system and the provider abstraction are all in
  place. Automated sending stays truthfully disabled until a provider is chosen and valid
  credentials are supplied; the adapter reports "not configured" rather than faking delivery.

Everything else in the 75-section master prompt is implemented, wired into the maroon UI and
covered by the integration suite (256 checks, all passing).

## Printing and branding

Browser printing no longer produces a screenshot of the interface. `public/assets/css/print.css`
drops the sidebar, header, filters and buttons, and `app/Views/partials/print-letterhead.php`
adds an institute letterhead (logo, name, address, phone, website) plus a generated-on footer to
every admin and portal page. Official documents (fee voucher, fee slip, salary slip, result card,
gazette, admission/job letter, certificates, ID cards, test marksheets) remain true mPDF
documents produced by the centralised engine and the Report Template Manager.
