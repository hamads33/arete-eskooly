# Workstation portability verification

Verified on 2026-10-24 using a clean local Git clone, freshly installed Composer
dependencies and a separate empty MySQL 8.4 database. No current school database was modified.

Passed:

- Locked Composer dependency installation and PHP platform requirements.
- All 48 database migrations from an empty database.
- Development administrator creation.
- New environment and runtime-directory setup; rerunning preserves the existing environment/key.
- Docker Compose configuration validation.
- Android TypeScript check and 20 tests across two suites using installed dependencies.

The full PHP suite against the clean baseline returned 1,083 passes and 19 failures:

- Exam create view rendering: 1.
- Historical student status, attendance and leaving-month vouchers: 8.
- Class-wide extra charges, their audit and financial reporting: 9.
- Teacher module permission at the admin dashboard: 1.

The suite also emitted fee-collection null-value and session-header warnings. These results
were obtained from commit `7666f43`, before the portability changes. Setup success does not
mean these application behavior checks pass. They remain unresolved application issues.

Not verified: installation on a second physical computer, fresh Android SDK/Gradle APK build,
private database/uploads restoration, live SMS delivery, Google OAuth and Linux multi-school
provisioning. The source and setup guide are available; identical school state still requires
the private items listed in [NEW-COMPUTER.md](NEW-COMPUTER.md).
