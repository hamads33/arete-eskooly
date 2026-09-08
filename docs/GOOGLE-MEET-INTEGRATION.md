# Google Meet Live Classes

Google Meet classes for administrators, teachers and students, built into the existing Live Classes
module. The design decisions and the pre-implementation survey are in
[GOOGLE-MEET-INTEGRATION-PLAN.md](GOOGLE-MEET-INTEGRATION-PLAN.md).

**Scope of this phase:** Calendar event creation, Meet conference generation, LMS audience, and the
join workflow. There is deliberately no attendance tracking, recording, participant-duration
reporting, transcription, or breakout-room control — Google exposes none of that to this
integration, and nothing in the product claims otherwise.

---

## 1. How the accounts work

**One Google account per institute.** An administrator authorises the official institute Google
account once. Teachers then schedule through that stored connection — they never see, hold, or
authorise Google credentials, and there is no per-teacher OAuth. Students authorise nothing at all;
their LMS session is what entitles them to a Meet link.

```
Teacher  →  LMS permission check  →  teacher_assignments scope check
                                  →  institute Google connection  →  Calendar API
```

Only an administrator can connect, reconnect, disconnect, or change the calendar configuration.

## 2. Google Cloud setup

1. Create or select a project at <https://console.cloud.google.com/>.
2. **APIs & Services → Library →** enable **Google Calendar API**.
3. **APIs & Services → OAuth consent screen**: choose Internal if the institute uses Google
   Workspace, otherwise External. Add the institute's own account as a test user while the app is
   unpublished.
4. **Credentials → Create credentials → OAuth client ID → Web application**.
5. Under **Authorised redirect URIs**, add exactly the value you will put in
   `GOOGLE_REDIRECT_URI` — for example
   `https://school.example.com/admin/integrations/google/callback`. A trailing-slash or scheme
   mismatch is the most common cause of a `redirect_uri_mismatch` error.
6. Copy the client ID and secret into the environment (below). No application source file is
   edited, and no secret is committed.
7. In the LMS, sign in as an administrator and open **Live Classes → Connect Google**.

### Scopes requested

| Scope | Why |
|---|---|
| `https://www.googleapis.com/auth/calendar.events` | Create, update and cancel the events this app creates. |
| `https://www.googleapis.com/auth/userinfo.email` | Show the admin which account is connected. |

No Drive, Gmail, or full-calendar scope is requested.

## 3. Environment variables

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://school.example.com/admin/integrations/google/callback
GOOGLE_CALENDAR_ID=primary
JOIN_BEFORE_START_MINUTES=10
```

`GOOGLE_CALENDAR_ID` may name a shared calendar instead of `primary`. `APP_KEY` must be set and
stable — it derives the token encryption key, so changing it invalidates every stored token and
forces a reconnect.

## 4. Token security

Access and refresh tokens are encrypted with **AES-256-GCM** (`App\Services\Google\TokenCipher`),
keyed by `hash('sha256', APP_KEY)`, and stored as `base64(iv|tag|ciphertext)` in
`google_oauth_connections`. GCM authenticates, so a tampered ciphertext fails to decrypt rather
than yielding attacker-chosen plaintext.

Tokens are never logged, never rendered, and never placed in a URL.
`GoogleConnectionRepository::safe()` exists so views can show connection state without the
ciphertext going anywhere near a template.

### Refresh and reconnect

`GoogleClientFactory::forInstitute()` decrypts the refresh token, exchanges it when the access
token has expired, and persists the replacement. If Google answers `invalid_grant` — a revoked or
expired consent — the connection flips to `reconnect_required` and the admin sees:

> Google account connection has expired or was revoked. Please reconnect Google Calendar.

Teachers see only "Google Live Classes are temporarily unavailable. Please contact the
administrator." No Google exception trace reaches any user.

## 5. Workflows

### Administrator

Live Classes shows the Google connection, quick actions, and then Live Now / Upcoming / Past /
Cancelled. An administrator can schedule, start an instant class, edit, cancel, retry a failed
Google sync, and see every teacher's classes.

### Teacher

**My Teaching → Live Classes.** Start Instant Class or Schedule Class, choosing only from the
classes and subjects they are actively assigned. The class and subject are re-checked against
`teacher_assignments` on every write, so editing the POST body does not widen their reach. Teachers
manage their own meetings; managing another teacher's requires `live-classes.manage-all`, which the
teacher role does not hold.

### Student

**Live Classes**, plus an Upcoming Live Classes tile on the dashboard. Each card shows title,
subject, teacher, start time, duration and status (Upcoming / Starting Soon / Live Now / Completed
/ Cancelled). Join opens Google Meet in a new tab. Students authorise nothing in Google here.

> **Guest admission.** Whether Google admits a student who is not signed in to a Workspace account,
> or admits them before the host arrives, depends on the organiser's Google Workspace and Meet
> settings. The LMS shows this note on the admin connection card, because it is not something the
> LMS can control or promise.

### Instant class

Start Instant Class → pick an authorised class and subject → optional title (defaults to
"*Subject* Live Class") → start time is now, duration defaults to 60 minutes → the event and
conference are created immediately → "Meeting Ready" with Open Google Meet and Copy Link. Students
in the audience see it at once.

### Join window

The Join button is active from `start_time − JOIN_BEFORE_START_MINUTES` (default 10) until
`end_time`, and never for a cancelled meeting. Before that the card says "Available 10 minutes
before class"; afterwards, "Completed".

The Meet URL is never rendered into a student's HTML. The button targets
`/portal/student/live-classes/{id}/join`, which re-checks institute, audience, time window and
cancellation, records the click, and only then redirects — to a URL validated as an `https`
`meet.google.com` address, so a corrupted row cannot turn the route into an open redirect.

### Edit and cancel

Editing patches the existing Google event with `conferenceDataVersion=1`, retaining
`conferenceData`, so the Meet link students already hold keeps working. Cancelling is a **soft**
cancellation: the Google event is cancelled, the row is marked `cancelled` with `cancelled_at` and
`cancelled_by`, students immediately lose the ability to join, and the record is retained for
audit. Google-integrated meetings are not hard-deleted.

## 6. What join tracking does and does not mean

`live_class_participants.join_clicked_at` records that **the student clicked the LMS Join Meeting
action**. It is not evidence they entered the Google Meet, and it must never be presented as
verified attendance. Google exposes no Meet attendance to this integration; if a real attendance
API is implemented later, it will need its own column and its own verification.

## 7. Timezone

Times are entered in the institute's configured timezone
(`institute_preferences.timezone`, default `Asia/Karachi`), converted to UTC for storage, and
rendered back in that timezone. The Calendar request carries the same timezone with an explicit
RFC3339 offset, so the LMS and Google agree and no hour drift appears. The suite asserts this
round trip.

## 8. Google and database consistency

An external API and MySQL cannot share a transaction, so writes go **Google first, database
second**:

| Operation | Failure behaviour |
|---|---|
| Create | If the local insert fails after the event exists, the event is deleted (compensation) and the failure logged. If compensation itself fails, the orphaned event id is logged for manual cleanup. |
| Update | The local edit is applied and `google_sync_status='failed'` recorded with the reason. Success is never claimed. |
| Cancel | Cancelled locally regardless, so the student's join right disappears; a Google failure is recorded and reported. |
| Conference pending | `google_sync_status='pending'`, no fabricated link, and a **Retry Google Sync** action for the admin. |

Google's conference creation is asynchronous, so a create is followed by up to three bounded
re-fetches (~0.5s / 1s / 2s). It never loops indefinitely.

## 9. Permissions

| Slug | Held by |
|---|---|
| `live-classes.view` | admin, principal, teacher, management-staff |
| `live-classes.manage` | admin, teacher |
| `live-classes.instant` | admin, teacher |
| `live-classes.manage-all` | admin only (via `'*'`) |
| `google-calendar.manage` | admin only — granted to no role, so only the `'*'` wildcard satisfies it |

`AdminAreaMiddleware` derives the requirement from the path segment: `live-classes` →
`live-classes.view` / `live-classes.manage`; `integrations` → `google-calendar.manage`.

## 10. Audit

Written to `audit_logs` via `PakistanErpRepository::audit()`, with the acting user, role, entity and
safe metadata — never a token: `google_calendar.connected`, `google_calendar.disconnected`,
`live_class.created`, `live_class.instant_created`, `live_class.updated`, `live_class.cancelled`,
`live_class.sync_retried`.

## 11. Automated tests

`php tests/run.php` — the Google Meet checks are in `tests/Feature/GoogleMeetFeatureTest.php`.

Google is **never contacted**: `CalendarClient` is an interface, and the suite injects
`Tests\Support\FakeCalendarClient` through the `ResolvesLiveClasses` test seam, which can simulate a
pending conference, a revoked token, and an unreachable API. Covered: token encryption round trip
and tamper rejection, connect / reconnect / refresh-failure state, OAuth restricted to
administrators, forged-state rejection, admin schedule / instant / edit / cancel / retry-sync,
pending-conference handling and bounded polling, Google-outage compensation, teacher scope
enforcement and `teacher_id` spoofing, student visibility and cross-class isolation, selected-student
audiences, every join-window boundary, cancelled and past refusal, join-click recording, Meet-URL
redirect validation, duplicate event mapping, tenant isolation, timezone round trip, and CSRF on
every mutation.

## 12. Manual Google integration test checklist

Automated tests cannot prove the real API works. Run this once per deployment, signed in as an
administrator, with the environment variables set.

1. Connect the institute Google account. Confirm the card shows **Connected as *account***.
2. Refresh the page — the connection persists.
3. Schedule an admin live class for a few minutes from now.
4. Open Google Calendar on that account — the event exists at the expected local time.
5. The event carries a Google Meet link, and the LMS shows the same link.
6. Sign in as a student of that class — the class appears on the dashboard and Live Classes page.
7. Within the join window, Join Meeting opens Google Meet.
8. As an admin, Start Instant Class.
9. The student sees it immediately.
10. As a teacher, schedule a class for an assigned class — it succeeds.
11. As the same teacher, submit an unassigned `class_id` (browser dev tools) — it is refused.
12. Edit a meeting's title and start time.
13. Google Calendar shows the change **and the same Meet link**.
14. Cancel the meeting.
15. The student's Join button is gone and the card reads Cancelled.
16. Revoke the app at <https://myaccount.google.com/permissions>, then try to schedule.
17. The admin sees the reconnect message; the teacher sees the "contact the administrator" message.
18. Reconnect and schedule again — it succeeds.

No browser automation is required.

## 13. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `redirect_uri_mismatch` | `GOOGLE_REDIRECT_URI` does not exactly match the authorised URI on the credential. Scheme, host, port and path must all match. |
| "Google did not return a refresh token" | The account had already consented. Remove the app at <https://myaccount.google.com/permissions> and connect again. |
| Connection keeps reverting to *reconnect required* | The refresh token is invalid, or `APP_KEY` changed after the tokens were stored. Reconnect. |
| Meetings stick at "Meet link pending" | Google has not finished creating the conference, or the account cannot create Meet conferences (some consumer or restricted Workspace accounts). Use **Retry Google Sync**; if it never resolves, check the account's Meet entitlement. |
| Students see "Live class is not available yet" during the window | The meeting has no Meet link yet — check its sync status. |
| Times are an hour off | Confirm the institute timezone in **Settings → Account**; the Calendar request uses that value. |
| "Google integration is not configured" | One of `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` is empty in the environment. |

## 14. Relationship to the legacy live-class page

`/admin/communication/live-class` is the original static room-code page, still backed by
`WorkflowRepository::createLiveClass()` and existing `provider='manual'` rows. It is untouched and
no longer linked from the sidebar; the Live Classes module is the Google Meet workflow. Legacy rows
remain valid — `room_code` merely became nullable, and the audience vocabulary was extended rather
than replaced.
