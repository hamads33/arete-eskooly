# Mobile LMS audit â€” 6 October 2026

## Safety and scope

Before any source edits, the clean working tree at `a5743c8` was tagged
`checkpoint-mobile-ux-2026-10-06`. All browser and functional QA uses localhost and a
separate MySQL database, `mobile_ux_qa_20261006`. No production deployment or production
database changes are part of this work.

Desktop defaults and existing desktop breakpoints remain authoritative. New phone
transformations use `@media screen and (max-width: 767px)`; drawer and tablet containment
use `max-width: 1023px`. Print must retain the existing table/document representation.

## Inventory and root causes

| Surface | Templates / shared patterns | Audit findings and treatment |
| --- | --- | --- |
| Shell | layouts/admin, layouts/portal, header, portal-header, sidebar, page-header | Existing accessible drawer retained; reduce phone header chrome, use title, 44px controls, compact page gutters. |
| Login / signup / install / errors | layouts/auth, auth/login, auth/signup, layouts/install, layouts/error | Single-column login already exists; remove oversized branding and vertical padding on phones. |
| Student | dashboard, courses, homework, notes, online-tests, take-test, test-result, attendance, fees, invoice, results, live-classes, recorded-lectures, documents, profile | Prioritize next classes/tests/homework, retain two metric columns, turn read-only records into labeled phone cards. |
| Submissions | submission-pages.js/css, submission-drafts, take-test | Existing native-like full-screen camera, gallery fallback, review/reorder/retake, IndexedDB drafts and upload retry retained. Improve portrait/landscape fit, touch targets, safe-area submit bar. |
| Employee / teacher | dashboard, profile, attendance, salary, classes, homework, documents; teacher attendance/homework/notes/tests/test-marks/announcements; teaching/live-classes | Read-only employee records become cards; keep marking tables and existing attendance cards functional; compact scoped forms and filters. |
| Parent | dashboard, profile, child | Reuse portal patterns; no student bottom navigation for parents. |
| Student / employee management | students/*, employees/*, guardians, teachers/assignments | Preserve existing mobile directory cards; single-column admissions and employee forms, compact toolbar actions. |
| Academics | classes, subjects, homework, notes, exams, class-tests, online-tests, results | Forms adapt; dense admin grading and results remain locally scrollable. |
| Attendance | students, employees, daily, classwise, student-report, employee-report | Existing marking cards retained; prevent implicit grid columns in narrow forms. |
| Finance | fees/*, accounts/*, salary/* | Keep specialized fee/salary interfaces and dense financial tables; contain local scrolling and retain all actions. |
| Communications | announcements, messaging, WhatsApp, live classes, recorded lectures | Compact mobile cards and internally scrolling dialogs; avoid external sends during QA. |
| SMS | dashboard, compose, bulk, templates, listing, devices, settings, automations, logs, message | Existing responsive SMS cards retained; inspect tabs, filters, composer and menus individually. |
| Settings / permissions | settings/*, roles/index, roles/edit | One-column forms, reset spanning fields, keep controls touchable and permission density intact. |
| Reports / printable documents | reports/*, student letters/cards, fee/salary slips, PDF templates | Preserve print; contain wide tables on screen, collapse long filters. |

The root issues are competing declarations in `responsive.css`, `portal.css`,
`premium-ui.css`, `ui-polish.css`, and `mobile-ui.css`, especially single-column metric
overrides, fixed 580â€“640px table minimums, and global overflow clipping. The shared mobile
file also contains unrelated existing announcement/status/print UI; those desktop rules
must remain unchanged. Consolidate the mobile owners rather than adding another override
stylesheet. Existing business rules, routes, permissions and storage remain unchanged.

## QA evidence

Screenshots, desktop geometry baselines and route results are stored under
`output/mobile-ux/` (ignored by Git). Desktop comparison covers 1366Ã—768, 1440Ã—900 and
1920Ã—1080 across dashboard, directory, admissions, settings, finance, tests, SMS and reports;
student desktop baselines cover dashboard, homework, fees, profile and account.

Final measured results and known limitations are recorded after implementation below.

## Implemented mobile experience

- Phone app bar: 60px, page context, accessible profile control, 44px menu controls.
- Existing drawer retains focus trapping, overlay, Escape and route-close behavior;
  adds swipe-close and makes the student bottom navigation inert while open.
- Student shortcuts use existing Home, Courses, Tests, Homework and Profile routes.
  The bar respects safe areas and disappears during assessment attempts.
- Student home prioritizes reminders, the next class, available tests and homework.
  Additional classes/tests and secondary analytics remain accessible in disclosures.
  Numeric metrics use two columns. Desktop nodes are restored when leaving mobile.
- Student/employee/parent read-only records become labeled cards: attendance,
  fees/payments/invoices, results, homework, classes, documents and profile details.
  Teacher homework, notes and tests use the same card pattern. Teacher attendance and
  marks become editable register cards with reachable Save controls.
- Dense admin records retain all columns in local scroll regions with a swipe hint.
  Bare tables receive a reversible mobile wrapper; secondary record actions move into
  More disclosures. Existing specialized directory/SMS/attendance cards remain intact.
- Long GET filters become expandable panels; POST fields remain visible. Receipt
  filters, account breakdowns, fee structures, messaging and report/salary headers
  receive scoped fixes for their actual overflow sources.
- Forms use one column, 16px inputs and larger controls. Dialogs/print-choice panels
  scroll within the available viewport. Mobile installation remains available in the
  profile menu without a banner covering workflow controls.
- Submission retains the existing camera, native file fallback, secure attachments,
  page review/retake/reorder, draft storage and retry logic. Portrait uses large capture
  controls and a full-width submit action; landscape places capture controls alongside
  the preview. Assessments have a mobile back link.
- A missing view-closure capture in student live classes was repaired: the existing
  join-window value is passed into the rendering callback, avoiding a PHP warning.
  Authentication, grading, fees, attendance calculations and API contracts were not
  changed by this mobile work.

## Breakpoints and desktop protection

Phone transformations: <=767px. Tablet containment/drawers: <=1023px. Existing defaults
remain authoritative from 1024px onward. Responsive changes do not duplicate records or
form fields. Print retains tables and opens temporarily grouped content.

Desktop geometry was compared at 1366x768, 1440x900 and 1920x1080. The eight admin
baseline surfaces cover dashboard, student directory/admissions, settings, fees, online
assessments, SMS and reports. Eighteen final student comparisons cover dashboard,
homework, fees, profile, account and photo assessment. Fifteen teacher comparisons cover
attendance, marking, tests, homework and notes. No final layout differences were found.
The dashboard/test list were additionally compared to a running checkpoint worktree
with identical QA data, because fixture counts and live-class availability had changed
since initial capture. This separated data changes from visual regressions.

## Final QA results

| Check | Result |
| --- | --- |
| Existing functional suite, including completed SMS/result integration | 1,078 passed; 0 failed |
| PHP syntax | All changed PHP templates/controllers/config checked; no failures |
| JavaScript syntax | mobile-ux.js, sidebar.js and sw.js pass node --check |
| Admin inventory | 107 destinations inspected at 320px, 390px and 768px; identified defects fixed/retested |
| Final admin sweep | All 107 at exactly 390px: no page overflow or rendered PHP errors |
| Admin breakpoint samples | 72 checks across eight critical workflows: passed |
| Student inventory | All 15 navigation destinations at 390px: passed |
| Student critical breakpoint samples | 60 checks across six workflows: passed |
| Staff/teacher inventory | 18 destinations inspected; scoped Exam Marks access-denied response preserved |
| Parent inventory | Six destinations including populated child details inspected |
| Populated teacher workflows | 20 checks at 320/390/768px and landscape: passed |
| Mark submission | Saved 18/20 and a synthetic remark in QA; reload confirmed both values |
| Navigation/interactions | Drawer open/close/lock, profile/logout, filter disclosure, More/Escape and desktop restoration passed |
| Camera portrait | Preview starts; 78px capture and 44px Close controls fit 390x844 |
| Camera landscape | 684x330 preview with side controls, 64px shutter and 44px Close fit 844x390 |
| Application console after final repairs | No new local-app warnings/errors in final targeted check; unrelated extension quota errors excluded |

Viewport coverage includes 320x740, 360x800, 375x812, 390x844, 412x915, 430x932,
480x900, 768x1024, 1024x768 and 844x390 landscape, plus the three desktop gate sizes.
Screenshots and JSON evidence are in output/mobile-ux/before and output/mobile-ux/after
and the adjacent audit JSON files. Browser zoom/viewport expansion was normalized;
final overflow checks use documentElement.clientWidth rather than the expanded mobile
innerWidth, so auto-scaling cannot hide horizontal overflow.

## Remaining validation limits

- Chrome's file chooser continued to reject setFiles with "Not allowed" after the user
  enabled extension file URL access and multiple reconnection/retry attempts. Actual
  browser gallery upload, preview/reorder/retake, file-draft recovery and final photo
  submission could not be completed. Existing submission feature tests pass; that is
  separate evidence and does not replace the blocked browser checks.
- No physical iPhone/Android device was attached. Real mobile keyboard/browser chrome,
  hardware capture, swipe gestures and device permission prompts still need device QA.
  The camera preview and layout were checked without capturing personal photographs.
- External SMS delivery, Google Meet joins, live financial writes and production
  deployment were deliberately outside the isolated UI QA. Existing feature gates,
  permissions and backend capabilities remain authoritative.
- Another session completed SMS/result work in commit 803445c. Its files were left
  untouched by this mobile pass. The combined current functional suite passed.

## Changed files

- `app/Controllers/PortalController.php`
- `app/Controllers/StudentOnlineTestsController.php`
- `app/Controllers/StudentPortalController.php`
- `app/Core/Controller.php`
- `app/Views/layouts/admin.php`
- `app/Views/layouts/auth.php`
- `app/Views/layouts/error.php`
- `app/Views/layouts/install.php`
- `app/Views/layouts/portal.php`
- `app/Views/partials/header.php`
- `app/Views/partials/portal-header.php`
- `app/Views/partials/pwa-ui.php`
- `app/Views/partials/student-mobile-nav.php`
- `app/Views/portal/employee/attendance.php`
- `app/Views/portal/employee/classes.php`
- `app/Views/portal/employee/dashboard.php`
- `app/Views/portal/employee/documents.php`
- `app/Views/portal/employee/homework.php`
- `app/Views/portal/employee/profile.php`
- `app/Views/portal/employee/salary.php`
- `app/Views/portal/parent/child.php`
- `app/Views/portal/parent/profile.php`
- `app/Views/portal/student/attendance.php`
- `app/Views/portal/student/dashboard.php`
- `app/Views/portal/student/documents.php`
- `app/Views/portal/student/fees.php`
- `app/Views/portal/student/homework.php`
- `app/Views/portal/student/invoice.php`
- `app/Views/portal/student/live-classes.php`
- `app/Views/portal/student/profile.php`
- `app/Views/portal/student/results.php`
- `app/Views/portal/student/take-test.php`
- `app/Views/portal/teacher/attendance.php`
- `app/Views/portal/teacher/homework.php`
- `app/Views/portal/teacher/notes.php`
- `app/Views/portal/teacher/test-marks.php`
- `app/Views/portal/teacher/tests.php`
- `config/pages.php`
- `docs/MOBILE-UX-AUDIT.md`
- `public/assets/css/mobile-ui.css`
- `public/assets/css/submission-pages.css`
- `public/assets/css/ui-polish.css`
- `public/assets/js/mobile-ux.js`
- `public/assets/js/sidebar.js`
- `public/sw.js`
