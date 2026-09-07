# Database Schema — Phase 1

## Institute ownership

`2026_09_06_000008_add_institute_memberships.php` adds `institute_users` with a unique user membership and indexed institute foreign key. Existing student/employee account links are migrated first; remaining legacy users retain the original institute. New institute signup creates this relationship inside the registration transaction. Auth context includes `institute_id`, and settings lookups resolve the current user's persisted membership.

## users

Stores administrator identity and authentication state: `id`, `name`, unique `email`, `password_hash`, `status`, `last_login_at`, `created_at`, and `updated_at`. Password hashes are never selected into the session context.

## roles and permissions

`roles` and `permissions` use unique stable slugs. `user_roles` and `role_permissions` are composite-primary-key pivot tables with cascading foreign keys. The schema supports multiple roles per user without adding module-specific rules prematurely.

## migrations

Records each applied migration once. Local development and verification run against the dedicated MySQL 8.4 Docker service. MySQL schema migrations use non-destructive `CREATE TABLE IF NOT EXISTS` statements and are recorded after successful execution; MySQL DDL is not wrapped in a PDO transaction because MySQL implicitly commits DDL. The Phase 1 definitions remain driver-aware, but SQLite is not used by the active environment.

## Phase 2 settings

`institutes` stores the institute identity and safe logo path. `institute_preferences` stores timezone and currency preferences. `fee_particulars` stores month and target scopes with indexed priority lookup data. `fee_invoice_accounts`, `rules`, and `grading_rules` persist invoice banks, sanitized audience policies, and non-overlapping grading ranges.

## Phase 3 academics

`classes` stores unique institute class names and tuition. Its teacher reference remains nullable until the Employees phase. `subjects` stores unique subject names per institute, and `class_subjects` is the foreign-keyed assignment pivot with total marks and duplicate prevention.

## Priority production modules

`students` preserves the complete approved identity, personal, family, father, and mother field contract. Registration is unique per institute; class and name are indexed. `employees` contains the complete approved employee master record, documented roles, status, compensation, contact, personal, and image fields.

`student_attendance` and `employee_attendance` enforce one record per person and date. Bulk saves validate the institute-owned active record set and transactionally upsert statuses, remarks, and supported time fields.

`account_heads` and `account_transactions` form one coherent income/expense ledger. Transactions retain creator, date, reference, head, and type; statement balances are calculated server-side from bounded filtered queries.

`fee_invoices`, `fee_invoice_items`, and `fee_payments` preserve monthly invoice snapshots and immutable payment history. One invoice is allowed per student/month. Fee resolution uses specific-month before all-month settings and Student > Class > All Students scope priority. Older unpaid balances are transactionally carried into the next invoice and marked `carried_forward`, preventing double counting. Each payment has a unique receipt number.

## Fast-track workflow schema

Migration `2026_09_06_000006` adds only new tables and indexes. `student_accounts` and `employee_accounts` link records to hashed `users` without mapping employee designations to RBAC roles. `student_promotions` provides a class-change audit trail. `salary_payments` enforces one payment per employee/month.

`homework` references a teacher, class and class-assigned subject. Exams use `exams`, `exam_subjects`, and `exam_marks`; class tests use `class_tests` and `class_test_marks`. Duplicate marks are prevented and services enforce class membership and maximum marks. Results resolve grades from `grading_rules`.

`certificates` stores typed recipients. `conversations`, `conversation_participants`, and `messages` provide participant isolation. `sms_settings` stores provider-neutral configuration and encrypted/masked secrets. `live_classes` stores audience targets and optional schedules without assuming an external provider.

## General settings completion

Migration `2026_09_06_000007` adds `theme` and `language` preferences to `institute_preferences` and creates institute-scoped `discount_types`. Class fee structure reuses `classes.monthly_tuition`; no duplicate fee table is introduced. Discount names are unique per institute and support percentage or fixed values with active/inactive state.
# Report templates

`report_templates` stores institute-scoped presentation overrides keyed by `(institute_id, template_type)`. It contains controlled text, page settings, colors, branding visibility flags, JSON extension settings, audit user IDs, and timestamps. Report business data remains in its source tables.

## Portal roles and memberships (migration 000015)

`2026_09_07_000015_create_portal_roles_and_memberships.php` adds no tables. It is additive and
idempotent, and it:

- inserts the `student` and `employee` rows into `roles`;
- inserts `portal.student` and `portal.employee` into `permissions`, linked via `role_permissions`;
- backfills `user_roles` for every existing `student_accounts` / `employee_accounts` row;
- backfills `institute_users` from the owning `students` / `employees` record, so portal queries
  remain tenant-scoped.

`institute_users.user_id` is the primary key, so a portal user belongs to exactly one institute.
# Additive Pakistan ERP schema (migration 000018)

New structures: `academic_sessions`, `academic_rule_profiles`, `academic_profile_grades`, `class_sections`, `academic_streams`, `teacher_assignments`, `guardians`, `guardian_students`, `guardian_accounts`, `institute_holidays`, `class_notes`, `fee_settings`, `fee_generation_runs`, `whatsapp_templates`, `whatsapp_logs`, and `audit_logs`.

Existing student, subject, attendance, homework, class-test, exam, invoice and payment tables receive additive columns. Existing identifiers and records are preserved; no reset migration is used.
