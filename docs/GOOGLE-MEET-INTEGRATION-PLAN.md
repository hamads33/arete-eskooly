# Google Meet Live Classes — Implementation Plan

Written 8 September 2026, after direct inspection of the existing code. It records the decisions
taken before the Google Meet work began.

## 1. Current implementation discovered

| Area | Finding |
|---|---|
| Live Classes | `CommunicationController::liveClass()` renders `app/Views/communication/live-class.php`, a **hardcoded static page**. `storeLiveClass()` writes through `WorkflowRepository::createLiveClass()`. There is no read-back screen — rows are written and never displayed. |
| `live_classes` table | Migration `000006`. Columns: `institute_id`, `title`, `room_code`, `audience_type ENUM('AS','AT','SC','SS','ST')`, `target_class_id`, `target_student_id`, `target_employee_id`, `scheduled_at`, `duration_minutes`, `message`, `created_by`. Unique `(institute_id, room_code)`. **No Google columns, no teacher, no section, no subject, no status.** |
| Navigation | `config/navigation.php:73` — `$item('Live Class', 'live-class', 'video', null, [], true)`: a **locked placeholder with a null route**. The only real route lives under Communication. |
| Routes | `GET/POST /admin/communication/live-class` only. |
| Roles | Real RBAC. `AdminAreaMiddleware` derives `<module>.view` / `<module>.manage` from `config/permissions.php` by first path segment plus HTTP method; an unmapped module stays admin-only. `'*'` (admin) satisfies every check. |
| Teacher assignments | `teacher_assignments(employee_id, class_id, subject_id, section_id, assignment_type ENUM('whole_class','subject','homeroom'), status)`. Authorization helper already exists: `PakistanErpRepository::teacherCan(employee, institute, class, subject)`. |
| Student enrollment | `students.class_id` (NOT NULL, FK), `students.section_id` (nullable, added in `000018`), `students.status ENUM('active','inactive')`. Sections live in `class_sections`. |
| Portals | `/portal/*` sits deliberately outside `/admin`. `PortalMiddleware` and `PortalController::identity()` resolve student/employee/parent from the `*_accounts` link tables — **never from request input**. Teacher authoring already exists at `/portal/employee/teaching/*`. |
| Encryption | `CredentialService` already performs AES-256-GCM through OpenSSL, keyed by `hash('sha256', APP_KEY, true)`, stored as `base64(iv|tag|ciphertext)`. That logic is private to the class. |
| Timezone | `institute_preferences.timezone`, default `Asia/Karachi`, read through `SettingsRepository::institute()`. `App` boots `date_default_timezone_set(config('app.timezone'))`. |
| Audit | `audit_logs` plus `PakistanErpRepository::audit($institute, $user, $action, $entityType, $entityId, $meta)`. Reused as-is. |
| Tests | No PHPUnit. `php tests/run.php` requires each `tests/Feature/*.php`, which returns `Closure(App): array<string,bool>`. |
| Code style | Controllers, services and repositories are minified to one line per method. New files match that. |

## 2. Decision: extend `live_classes`, do not create `meetings`

Per §12 of the brief. `live_classes` already *is* the live-class domain — institute-scoped,
audience-typed, scheduled, FK-constrained, and referenced by `AcademicRepository`'s class in-use
check and its cascade delete. A parallel `meetings` table would fork that domain, break those two
references, and orphan the existing rows.

The existing `audience_type` ENUM is eSkooly's (`AS` all students, `AT` all teachers, `SC` single
class, `SS` single student, `ST` single teacher). It is kept and **extended** with `SEC` (section)
and `SEL` (selected students) rather than replaced, so existing rows stay valid.

`room_code` was a self-hosted room identifier carrying a unique index. Google-backed rows have no
room code, so the column becomes nullable; MySQL unique indexes permit repeated NULLs, so nothing
else has to change.

## 3. Schema changes — migration `2026_09_08_000020`

`live_classes` gains:

| Column | Purpose |
|---|---|
| `provider ENUM('manual','google_meet')` default `manual` | Distinguishes legacy rows from Google rows. |
| `teacher_id` FK `employees` | The hosting teacher, set from the authenticated teacher and never from the form. |
| `subject_id` FK `subjects`, `section_id` FK `class_sections` | Audience precision. |
| `created_by_role` | Who created it (`admin` / `teacher`). |
| `description`, `start_time` (UTC), `end_time` (UTC), `timezone` | Real scheduling. `scheduled_at` is retained for legacy rows. |
| `google_event_id`, `google_calendar_id`, `meet_link`, `conference_status` | Google linkage. |
| `status ENUM('scheduled','completed','cancelled','google_error')` | Persisted lifecycle. Live and Starting Soon are **derived** from the times rather than stored (§32). |
| `google_sync_status ENUM('synced','pending','failed')`, `last_google_error` | Sync truthfulness (§31). |
| `cancelled_at`, `cancelled_by` | Soft cancellation (§29). |

Unique `(institute_id, google_event_id)` prevents duplicate Google event mapping.

New tables:

- `google_oauth_connections` — one row per `(institute_id, provider)`, unique. Encrypted access
  and refresh tokens, `account_email`, `calendar_id`, `scopes`,
  `status ENUM('connected','reconnect_required','disconnected')`, `last_error`.
- `live_class_participants(live_class_id, student_id, join_clicked_at)` — used **only** for the
  `SEL` audience. Class and section audiences derive membership from `students` live, so a student
  moved between classes immediately gains and loses the right meetings (§14).

`join_clicked_at` is named for what it measures: the student clicked our Join action. It is **not**
Google Meet attendance (§15), and the migration comments say so at the column.

## 4. Services

`live_class_classes(live_class_id, class_id)` maps one Google event and Meet link to every selected
class. Existing single-class rows are backfilled from `target_class_id`.

| Service | Responsibility |
|---|---|
| `GoogleClientFactory` | The single place `Google\Client` is configured: env credentials, redirect URI, scopes, offline access. Restores the decrypted token, refreshes when expired, persists the refreshed token, and throws `GoogleReconnectRequired` on `invalid_grant`. |
| `TokenCipher` | AES-256-GCM authenticated encryption keyed from `APP_KEY`, extracted so credentials and OAuth tokens share one audited implementation. |
| `GoogleOAuthService` | Authorization-URL construction with a session-bound `state`, callback exchange, connect/disconnect, connection status. |
| `GoogleCalendarService` | A thin domain wrapper over the Calendar API: `createEvent`, `updateEvent`, `cancelEvent`, `fetchMeetLink`. It speaks through the `CalendarClient` interface so tests substitute a fake (§45). |
| `LiveClassService` | Orchestration: schedule, instant, edit, cancel, retry-sync, plus the Google-to-database compensation logic. |
| `LiveClassAuthorizationService` | "May this actor touch this class, subject or meeting?" for admin, teacher and student. |

`Google\Client` is never touched from a controller.

## 5. Routes

Admin, following the existing `/admin` conventions and the router's GET/POST-only design:

```
GET  /admin/live-classes                     LiveClassesController::index
GET  /admin/live-classes/create              ::create
POST /admin/live-classes                     ::store
POST /admin/live-classes/instant             ::instant
GET  /admin/live-classes/{id}/edit           ::edit
POST /admin/live-classes/{id}                ::update
POST /admin/live-classes/{id}/cancel         ::cancel
POST /admin/live-classes/{id}/sync           ::sync
GET  /admin/integrations/google/connect      GoogleIntegrationController::connect
GET  /admin/integrations/google/callback     ::callback
POST /admin/integrations/google/disconnect   ::disconnect
```

Teacher and student routes live under `/portal`, not `/teacher` and `/student`, because
`routes/web.php` applies `AdminAreaMiddleware` to all of `/admin` and the portals are already
established at `/portal/employee/teaching/*` and `/portal/student/*`:

```
GET  /portal/employee/teaching/live-classes              TeacherLiveClassController::index
POST /portal/employee/teaching/live-classes              ::store
POST /portal/employee/teaching/live-classes/instant      ::instant
POST /portal/employee/teaching/live-classes/{id}         ::update
POST /portal/employee/teaching/live-classes/{id}/cancel  ::cancel
GET  /portal/student/live-classes                        StudentPortalController::liveClasses
GET  /portal/student/live-classes/{id}/join              ::joinLiveClass
```

## 6. Permissions

Added to `config/permissions.php` under the `live-classes` and `integrations` modules, and seeded
by the migration:

`live-classes.view`, `live-classes.manage`, `live-classes.instant`, `live-classes.manage-all`,
`google-calendar.manage` (admin only), `portal.live-classes.join`.

The teacher role gains view, manage and instant; admin keeps `'*'`. `google-calendar.manage` maps
to the `integrations` module, so only administrators reach the OAuth routes.

## 7. Visibility rules

**Student** sees a meeting when all of these hold: same institute; student `status='active'`; and
the audience matches — `AS`, or `SC` where `target_class_id = students.class_id`, or `SEC` where
`section_id = students.section_id`, or `SS` targeting them, or `SEL` with a participant row.
Guessing an id fails because the join *is* the filter — there is no "fetch, then check".

For a multi-class `SC` meeting, the class match also uses `live_class_classes`. One local meeting
maps to one Google event and one Meet link; it is not duplicated per class. Multi-class scheduling
requires an active teacher assignment for every selected class and does not accept a subject.

**Teacher** sees the meetings they created plus meetings on classes and subjects they hold an
active assignment for. Mutation additionally requires ownership unless the actor holds
`live-classes.manage-all`. `teacher_id` always comes from the session, never the form (§43).

**Join window**: enabled from `start_time - JOIN_BEFORE_START_MINUTES` (default 10, configurable in
`config/app.php`) until `end_time`, and never for a cancelled meeting. The Meet URL is not rendered
into the HTML at all — the student receives `/portal/student/live-classes/{id}/join`, which re-runs
every check server-side and only then redirects to a validated Google Meet URL (§24).

## 8. Google API error handling

Google and MySQL cannot share a transaction, so the order is **Google first, database second**,
with compensation. If the event is created and the local insert then fails, the new Google event is
deleted and the failure logged. If Meet conference generation is pending, the event is re-fetched
with bounded backoff (three attempts, roughly 0.5s / 1s / 2s); still pending leaves
`google_sync_status='pending'` and an admin **Retry Google Sync** action — never a silent claim of
success. `invalid_grant` flips the connection to `reconnect_required`. Every user-facing message is
a plain sentence; Google exception traces go to the log only.

## 9. Timezone

Times are entered in the institute timezone, converted to UTC for storage, and rendered back in the
institute timezone. The Calendar request carries that same institute timezone, so the LMS and
Google agree and no one-hour drift can appear.

## 10. Testing strategy

`tests/Feature/GoogleMeetFeatureTest.php`, registered in `tests/run.php`. Google is never contacted:
`CalendarClient` is an interface and the suite injects `FakeCalendarClient`, which can be told to
return a pending conference or to throw. Coverage: OAuth start restricted to admin, state
validation, encrypted-token round trip, refresh success and failure, admin schedule / instant /
edit / cancel / sync-failure, teacher authorized versus rejected class, one-event multi-class scheduling, student visibility and
isolation, join-window boundaries, cancelled and past denial, join-click logging, duplicate event
mapping, institute isolation, timezone conversion, and CSRF on every mutation.
