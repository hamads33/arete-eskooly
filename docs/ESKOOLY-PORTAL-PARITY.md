# eSkooly Student & Employee Portal — Research and Parity Matrix

Researched 7 September 2026 against the authenticated eSkooly web application at `https://eskooly.com/bb/`, plus public eSkooly sources. Read-only: no reference data was created or modified, and no subscription or access control was bypassed.

## 1. How eSkooly structures portal access

`https://eskooly.com/login.php` presents three sign-in roles as radio inputs named `s`:

| Value | Label |
| --- | --- |
| `ADMN` | Admin |
| `EMP` | Employee |
| `STD` | Student |

There is also a dedicated student entry point. `https://student.eskooly.com/` issues a `302` to `https://www.eskooly.com/bb/StudentPortal/`, which lands on `/bb/StudentPortal/signin.php` — a sign-in page offering only the `STD` role.

**Consequence, and it is the central finding of this research:**

- **Students** get a *separate application* at `/bb/StudentPortal/`.
- **Employees** do **not**. They sign in to the *same* admin application at `/bb/` with role `EMP`, and see the ordinary admin UI filtered down by Roles & Permissions. There is no `/bb/StaffPortal/`, `/bb/EmployeePortal/`, or `/bb/TeacherPortal/` — all three return eSkooly's "This Page Does Not Exist".

## 2. The eSkooly Student Portal is small — complete page inventory

Determined by requesting each candidate path under `/bb/StudentPortal/` and separating eSkooly's own 404 page from pages that exist (some redirect to `signin.php` because they are session-protected). This is an existence check against a normal HTTP response, not an authorization bypass.

**Every page that exists (10):**

| Page | Purpose |
| --- | --- |
| `index.php` | Portal dashboard (session-protected) |
| `account.php` | Account settings |
| `feeslip.php` | Fees paid slip |
| `examresult.php` | Exam result |
| `reportcard.php` | Result / report card |
| `classtest.php` | Class test result |
| `homework.php` | Homework |
| `admissionletter.php` | Admission letter (session-protected) |
| `MessageBox.php` | Internal messaging |
| `liveclass.php` | Live class — **excluded from our scope** |

**Confirmed absent** (all return eSkooly's 404, each probed under several plausible names): attendance (`attendance.php`, `att.php`, `myatt.php`, `allatt.php`, `attreport.php`, `stdatt.php`, `attendence.php`, `myattendence.php`), profile (`profile.php`, `myprofile.php`, `myinfo.php`, `studentinfo.php`), `timetable.php`/`tt.php`, `certificates.php`, `idcard.php`, `subjects.php`, `datesheet.php`, `behaviour.php`, `skills.php`, `notice.php`, `library.php`, `transport.php`, `downloads.php`, `syllabus.php`, `teachers.php`, `classmates.php`, `store.php`, `settings.php`, `changepassword.php`.

> **Do not confuse this with `pro.eskooly.com/docs`.** That documentation describes a *different product* with Dormitory, Transport, Library and a much larger Student/Parent panel. It does not describe the `/bb/` application and was not used as a parity source.

## 3. Parity matrix — Student portal

| # | eSkooly StudentPortal | Our implementation | Status |
| --- | --- | --- | --- |
| 1 | `index.php` — dashboard | `/portal/student` | **COMPLETE** |
| 2 | `account.php` — account settings | `/portal/account` | **COMPLETE** |
| 3 | `feeslip.php` — fees paid slip | `/portal/student/documents/fee-slip/{payment}` (branded PDF) | **COMPLETE** |
| 4 | `examresult.php` — exam result | `/portal/student/results` | **COMPLETE** |
| 5 | `reportcard.php` — result card | `/portal/student/documents/report-card/{exam}` (branded PDF) | **COMPLETE** |
| 6 | `classtest.php` — class test result | `/portal/student/results` (Class Tests section) | **COMPLETE** |
| 7 | `homework.php` — homework | `/portal/student/homework` | **COMPLETE** |
| 8 | `admissionletter.php` — admission letter | `/portal/student/documents/admission-letter` (branded PDF) | **COMPLETE** |
| 9 | `MessageBox.php` — messaging | `/portal/messages` | **COMPLETE** |
| 10 | `liveclass.php` — live class | — | **EXCLUDED** by project scope (§5/§44) |

**Result: full parity on every non-excluded page.**

### Where we exceed eSkooly

These have no equivalent in eSkooly's student portal and are ours alone:

- `/portal/student/attendance` — attendance summary and record with a date-range filter
- `/portal/student/profile` — admission and guardian record
- `/portal/student/fees` and `/portal/student/fees/{id}` — invoice list and per-invoice particulars on screen (eSkooly only offers the printable slip)
- Certificates, listed on `/portal/student/results`

## 4. Parity matrix — Employee

eSkooly has no employee portal to copy. An `EMP` login enters the admin app with a permission-filtered menu, and **Roles & Permissions is locked** in the web edition — its tooltip reads *"This option is available in Desktop subscription only."* The employee experience therefore **could not be observed**, and no amount of further web research will reveal it.

Rather than invent it, we built a self-service employee portal by direct analogy with the student portal, using only data our schema already holds:

| Capability | Route | Basis |
| --- | --- | --- |
| Dashboard | `/portal/employee` | Analogous to student `index.php` |
| Profile | `/portal/employee/profile` | Our `employees` record |
| Attendance | `/portal/employee/attendance` | Our `employee_attendance` |
| Salary history | `/portal/employee/salary` | Our `salary_payments` |
| Assigned classes | `/portal/employee/classes` | Our `classes.teacher_reference` |
| Homework assigned | `/portal/employee/homework` | Our `homework.teacher_id` |
| Job letter (PDF) | `/portal/employee/documents/job-letter` | eSkooly admin `empregal.php` |
| Salary paid slip (PDF) | `/portal/employee/documents/salary-slip/{payment}` | eSkooly admin `empregfs.php` |
| Messaging | `/portal/messages` | Analogous to `MessageBox.php` |
| Account settings | `/portal/account` | Analogous to `account.php` |

**Evidence confidence: MEDIUM.** Derived by analogy, not observation. Recorded here rather than presented as verified parity.

### Deliberate architectural divergence

eSkooly gives employees a filtered *admin* UI; we give them a *self-service portal*. Matching eSkooly's model would require the Roles & Permissions system that `docs/CURRENT-IMPLEMENTATION-AUDIT.md` records as `SCAFFOLD-ONLY` — 10 of 12 seeded permission slugs are checked nowhere, and `/admin` is globally admin-gated. Building that filtered-admin experience is the natural next phase; the portal does not block it.

## 5. Security notes

Portal parity did not relax any control:

- No portal route accepts a student or employee identifier. `PortalRepository` resolves the owning record from the session user id, and every query is bound to it and to the owner's institute.
- Document routes verify ownership before rendering: a fee slip for another student's payment returns 404, as does a report card for an exam the student did not sit.
- Account settings require the current password before an email or password change, so a hijacked session cannot lock the owner out. A password change also clears the stored managed-credential cipher, so the admin "reveal password" view cannot display a stale value.
- Messaging reuses `WorkflowRepository`, whose participant and institute checks already prevent reading or posting into a foreign conversation.
- All portal mutations are CSRF-protected.

Covered by 39 checks in `tests/Feature/PortalFeatureTest.php`; full suite 206 passing.

## 6. Sources

| Source | Used for |
| --- | --- |
| `https://eskooly.com/login.php` | Role model (`ADMN`/`EMP`/`STD`) |
| `https://student.eskooly.com/` → `https://www.eskooly.com/bb/StudentPortal/` | Student portal location |
| `https://eskooly.com/bb/StudentPortal/signin.php` | Student-only sign-in, `STD` role |
| `https://eskooly.com/bb/StudentPortal/*` | Page inventory (existence probing) |
| `https://eskooly.com/bb/` sidebar | Admin menu and lock tooltips (43 locked items) |
| `https://eskooly.com/features.php` | Public feature list (23 names) |
| `https://desktop.eskooly.com/` | "Separate User Portals" claim for Principal, Management staff, Accountant, Store Manager, Teachers, Parents, Students |
| `https://eskooly.com/tutorials.php`, `https://knowledgebase.eskooly.com/` | Public help material (thin; setup-level only) |
| `https://pro.eskooly.com/docs/` | **Rejected** — different product, not used for parity |
