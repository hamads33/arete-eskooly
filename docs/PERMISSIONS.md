# Phase 1 Permissions

The development administrator role receives these initial permission slugs:

- `dashboard.view`
- `settings.view`
- `students.view`
- `employees.view`
- `accounts.view`
- `fees.view`
- `salary.view`
- `attendance.view`
- `homework.view`
- `exams.view`
- `reports.view`
- `communication.view`

All `/admin/*` routes currently require a valid active user through `AuthMiddleware`. Module-level permission middleware is ready, but individual mutation permissions will be added only with the relevant approved backend phase. Views must use `can()` only for presentation; controllers/routes must enforce authorization independently.

Phase 2 settings controllers enforce `settings.view` in addition to authentication for both direct reads and mutations. Phase 3 routes currently use authenticated-admin access; finer-grained academic mutation permissions remain a role-management enhancement and are not inferred from employee designations.

`hasRole('admin')`, `can('permission.slug')`, `check()`, `guest()`, `user()`, and `auth()` read the refreshed safe authentication context. Employee designations must not be treated as application roles without an explicit mapping.
# PDF reports and templates

PDF report routes require an authenticated administrator. Report template viewing and mutation additionally checks `settings.view`; save/reset requests require CSRF validation. Every record lookup and template query is constrained to the active institute.

## Portal roles (migration 000015)

Two roles and two permissions were added to enable self-service access:

| Role slug | Permission slug | Granted to |
| --- | --- | --- |
| `student` | `portal.student` | Every row in `student_accounts` |
| `employee` | `portal.employee` | Every row in `employee_accounts` |

Background: `CredentialService::create` previously inserted a `users` row and the
`student_accounts` / `employee_accounts` link but **no** `user_roles` and **no**
`institute_users` row. `AuthService::attempt` rejected any user without the `admin` role, so
managed logins failed with "Student and employee portals are undergoing testing". Migration
`2026_09_07_000015` seeds the roles/permissions and backfills both links for existing
accounts; `CredentialService::grant()` now does the same for every new login (idempotent).

`AuthService::attempt` accepts `admin`, `student` or `employee`, and still returns
`restricted` for a user holding none of them.

Note the distinction preserved here: `employees.role` is a job designation (Principal,
Teacher, Accountant…) and is **not** an application RBAC role. Every employee login receives
the single `employee` role regardless of designation.

Portal authorization is enforced by `PortalMiddleware` (requires `student` or `employee`) plus
per-request ownership: `PortalRepository` resolves the owning record from the session user id,
and no portal route accepts a student or employee identifier.
# Pakistan ERP permission additions

- `portal.parent`
- `teacher-assignments.manage`
- `notes.view`
- `notes.manage`
- `whatsapp.manage`

Parent child access is additionally enforced through `guardian_students`; route IDs cannot bypass ownership. New admin routes remain closed by default through `AdminAreaMiddleware` and the module permission map.
# Live class permissions (migration 000020)

| Slug | Granted to |
| --- | --- |
| `live-classes.view` | admin (`*`), principal, teacher, management-staff |
| `live-classes.manage` | admin (`*`), teacher |
| `live-classes.instant` | admin (`*`), teacher |
| `live-classes.manage-all` | admin only — no role grant exists |
| `google-calendar.manage` | admin only — deliberately granted to no role |

`google-calendar.manage` gates `/admin/integrations/*`. It is granted to no role, so only the admin
wildcard satisfies it: a teacher can neither reach the OAuth handshake nor see any part of the
institute's Google credentials.

Teacher authority over live classes is not a permission alone. `live-classes.manage` lets a teacher
create and edit; *which* class they may target is decided by `teacher_assignments` through
`LiveClassAuthorizationService::teacherMayHost()`, re-checked server-side on every write. Editing or
cancelling someone else's meeting additionally requires `live-classes.manage-all`.

Students need no live-class permission: audience membership is the authorisation, evaluated inside
the query that lists their meetings.
