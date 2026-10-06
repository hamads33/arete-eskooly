# SMS Center (LMS side)

The SMS Center queues SMS inside the LMS. A paired **Android SMS bridge** app later takes messages
from the queue and sends them through the institute's SIM (see `android-sms-gateway.md`).
No paid SMS provider is involved. **Nothing is marked "sent" until a device confirms it**; until
then the UI says "Queued for SMS Gateway".

## Where things live

| Piece | Location |
|---|---|
| Admin pages | `app/Controllers/SmsCenterController.php`, `app/Views/sms/*`, `public/assets/{css/sms-center.css,js/sms-center.js}` |
| Gateway API | `app/Controllers/SmsGatewayApiController.php`, `routes/api.php` |
| Phone normalisation | `App\Services\Sms\PhoneNumber` (the only place numbers are normalised) |
| Segment counting | `App\Services\Sms\SmsText` (mirrored in `sms-center.js`) |
| Templates & variables | `SmsTemplates`, `SmsTemplateRenderer` |
| Queue & state machine | `SmsQueue` |
| Recipients from LMS data | `SmsRecipients` |
| Compose / bulk | `SmsDispatcher` |
| Automations | `SmsAutomation` |
| Devices & tokens | `SmsGateway` |
| Audit trail | `SmsLogger` → `sms_logs` |
| Settings | `SmsSettings`, stored in the existing `sms_settings` row (`provider = 'android_gateway'`) |
| Daily job | `scripts/sms_daily.php` |
| Migration | `database/migrations/2026_09_27_000035_create_sms_center.php` |
| Tests | `tests/Feature/SmsCenterFeatureTest.php` |

## Tables

All tables are scoped by `institute_id`.

- `sms_messages`: one row per SMS (never a comma-separated list). Holds status, priority,
  schedule, attempts, device, SIM, failure details, `batch_id` (bulk) and `dedupe_key`
  (unique per institute; stops an event from queuing twice).
- `sms_templates`: built-in templates (`is_system`) back the automations. They can be edited
  or deactivated, but not deleted.
- `sms_gateway_devices`, `sms_gateway_tokens`: devices, plus SHA-256 hashes of their pairing
  codes and access tokens.
- `sms_automation_rules`: one rule per event per institute.
- `sms_logs`: audit trail (actor: user, device or system).
- `sms_api_rate_limits`: per-minute counters for the API.

## Recipients and phone numbers

| Recipient type | Phone taken from |
|---|---|
| Student | `students.mobile` |
| Parent / guardian of a student | primary linked guardian (`guardians.mobile`), else `guardian_mobile`, `father_mobile`, `mother_mobile` |
| Staff | `employees.mobile` |
| Manual | typed number |

Numbers are normalised to E.164 using the **default country code** setting (92 by default):
`03001234567`, `3001234567`, `923001234567` and `+923001234567` all become `+923001234567`.
Numbers with a country code (`+44…`, `0044…`) are kept. Pakistani landlines are rejected, since SMS
needs a mobile number. Invalid or missing numbers are listed as *skipped* with a reason; they
never fail silently.

## Templates and variables

The allowed variables are listed in `SmsTemplateRenderer::VARIABLES`: `school_name`,
`school_phone`, `recipient_name`, `student_name`, `parent_name`, `class_name`,
`registration_no`, `date`, `attendance_status`, `fee_month`, `fee_amount`, `due_date`,
`invoice_no`, `amount_paid`, `receipt_no`, `exam_name`, `marks`, `percentage`, `grade`,
`announcement_title`.

- Unknown variables are rejected when a template is saved.
- A variable with no value for a recipient stops that recipient's message (bulk: they are
  listed as skipped). The queue also refuses any text still containing `{{…}}`.
- Money values include the institute's currency symbol.

## Bulk SMS

Audience: parents, students or staff. Filters, all backed by real data:

- everyone
- a class
- a section
- students with unpaid invoices (adds `fee_amount`, `fee_month`, `due_date`)
- attendance status on a date
- students with marks in an exam (adds `exam_name`, `marks`, `percentage`)

Preview shows the selected count, the valid count, and skipped recipients with reasons.
Batches of 50 or more need an explicit confirmation tick. A parent with two children who would
get identical text receives one SMS.

## Automations

| Event | Hook | Dedupe key | Notes |
|---|---|---|---|
| Student marked absent (or Late/Leave, configurable) | `AttendanceService::saveStudents` (admin and teacher) | `attendance:{student}:{date}` | Today's register only. Correcting to Present cancels the unsent alert; re-marking absent revives it. |
| Fee due in N days | `scripts/sms_daily.php` | `fee_due:{invoice}` | Covers the whole window, so a missed run skips nobody. |
| Fee overdue N days | `scripts/sms_daily.php` | `fee_overdue:{invoice}` | Only for invoices due in the last 60 days. |
| Payment received | `FeeService::collect` | `payment:{payment}` | |
| Result published | exam status → Published | `result:{exam}:{student}` | Adds percentage and marks. |
| Admission confirmed | new student saved from the form | `admission:{student}` | CSV imports do not trigger it. |
| Announcement | "Also send via SMS" on the Announcements page | `announcement:{id}:{phone}` | Shows a recipient count first; a future publish time schedules the SMS. |

All automations are **off by default**. They also respect the matching master switch in SMS
Settings and the template's Active flag. A handler never throws into the module that triggered
it: attendance and payments always save.

### Cron

```
0 9 * * * php /path/to/app/scripts/sms_daily.php
```
Needed only for fee due/overdue reminders. Scheduled SMS, offline detection and stale
reservations work without cron. Stale reservations are also released on every gateway poll.

## Permissions

Mapped into the existing RBAC. They appear under **Roles & Permissions → SMS Center**:

| Permission | Allows |
|---|---|
| `sms.view` | Open the SMS Center: dashboard, queue lists, templates, automations, devices (read-only) |
| `sms.compose` | Compose single SMS |
| `sms.bulk_send` | Bulk SMS, and "Also send via SMS" on announcements |
| `sms.templates.manage` | Create, edit and delete templates |
| `sms.queue.manage` | Cancel, retry, requeue, delete and reschedule messages |
| `sms.settings.manage` | SMS Settings and automations |
| `sms.gateway.manage` | Register devices, pairing codes, tokens, disable and delete |
| `sms.logs.view` | Logs and per-message history |

The admin role (`*`) has everything. No other role gets these by default. Students, parents and
teachers never see the SMS Center.

## Settings

Stored in `sms_settings.configuration_json`, with the enabled flag in `sms_settings.enabled`.
Available settings:

- module on/off
- default country code
- manual and bulk switches
- queue: batch size, max retries, retry delay, default priority, reservation timeout, pause sending
- daily and monthly limits (0 = unlimited; cancelled messages don't count)
- quiet hours (gateways get nothing during them)
- automation master switches
- gateway: offline timeout, heartbeat interval, default device, default SIM slot

## Connecting the Android bridge later

1. Make sure the site is served over HTTPS and `APP_URL` is the public HTTPS URL.
2. **SMS Center → Gateway Devices → Register Device**, then give it a name.
3. Copy the pairing code shown once, together with the API base URL on the same page.
4. In the app, enter both. The app calls `/register` and stores its token.
5. The device shows **Connected** after its first heartbeat, and starts draining the queue.
6. To replace a phone: disable or delete the old device and register the new one.
   **Regenerate Token** issues a new token and invalidates the old one.
