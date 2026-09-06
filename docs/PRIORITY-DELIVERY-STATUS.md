# Delivery Status

Updated: 6 September 2026

## Environment

- Project: `C:\Users\OGDCL\Desktop\arete-eskooly`
- Application: `http://127.0.0.1:8081`
- MySQL: port `3307`, database `school_app`
- Migrations: current through `2026_09_06_000007_complete_general_settings.php`

Credentials are sourced from the uncommitted `.env`; passwords are not stored in project documentation.

## Complete

- Authentication, RBAC helpers, sessions, CSRF and admin-role enforcement.
- Institute settings, rules, grading, classes and subjects.
- General Settings fee structure, discount types, theme/language preferences, invoice accounts, rules and grading.
- Student and employee master records.
- Attendance entry and student, employee and classwise reports.
- Income, expense, ledger, fee invoicing, carry-forward, partial payments, receipts and outstanding balances.
- Student letters, ID cards, bounded basic list, secure credentials, promotions and report cards.
- Employee letters, secure credentials, salary payments and persisted slips.
- Homework listing, filters, safe attachments, create and update.
- Exam creation/setup, transactional marks, student/class results and configured grading.
- Class-test marks and filtered result/performance reports.
- Admission and parent-contact reports with bounded pagination.
- Certificate generation, listing and deletion.
- Participant-isolated internal messaging with unread/read state.
- Provider-neutral SMS configuration with encrypted secret storage and masked reads.
- Deployment, migration, security, backup and restore documentation.

## Deferred

- Live Class view completion, at user request.
- A concrete SMS provider adapter until a provider and credentials are selected.
- Production deployment and production data import.

## Automated verification

The consolidated suite validates persisted behavior, transactions, rollback, duplicate prevention, class/subject ownership, password hashing, marks bounds, configured grade resolution, certificate recipient rules, messaging isolation, secret masking, route rendering, authorization and CSRF.
