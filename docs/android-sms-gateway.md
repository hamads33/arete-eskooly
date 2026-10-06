# Android SMS Gateway API (v1)

This is the contract between the LMS and the **Android SMS bridge** app. The app sends
SMS that the LMS has queued, using the institute phone's SIM. It never touches the
database; everything goes through this API.

```
LMS web app ──queue──▶ sms_messages ◀──HTTPS/JSON── Android bridge ──SIM──▶ parent / student
```

Base URL: `https://<your-lms-domain>/api/sms-gateway/v1`
(shown on **SMS Center → Gateway Devices**).

## 1. Lifecycle the app implements

1. Admin opens **SMS Center → Gateway Devices → Register Device**. The LMS shows a one-time
   **pairing code** (`XXXX-XXXX-XXXX`, valid 30 minutes, single use).
2. The user types the base URL and pairing code into the app. The app calls `POST /register`
   and receives a long-lived **access token**. It stores the token in Android Keystore /
   EncryptedSharedPreferences.
3. Every `heartbeat_interval_seconds` (default 60), the app calls `POST /heartbeat`.
4. The app polls `GET /messages?limit=N` (every `poll_interval_seconds`, or immediately when
   heartbeat reports `queued_messages > 0`). Returned messages are **reserved for this device**.
5. For each message the app calls `POST /messages/{id}/processing`, sends the SMS with
   `SmsManager`, then reports `POST /messages/{id}/sent` or `POST /messages/{id}/failed`.
   Several results can be reported in one `POST /messages/bulk-status`.
6. The admin sees status, device, SIM and timestamps in the SMS Center.

## 2. Authentication

* All endpoints except `/register` need `Authorization: Bearer <access token>`.
  If a proxy strips `Authorization`, send the same value as `X-Device-Token: <token>`.
* Tokens look like `smsgw_<64 hex>`. The server stores only their SHA-256 hash, and a token
  is shown exactly once: to the app on `/register`, or to the admin on "Generate Token".
* A token stops working when the admin regenerates or revokes it, or disables or deletes
  the device.
* Use HTTPS in production. Never log the token or put it in URLs.
* Rate limits: 120 requests per minute per device; `/register` 10 per minute per IP. Over the
  limit you get `429 RATE_LIMITED`; wait until the next minute.

## 3. Response format

Every response is JSON.

```json
{ "success": true, ... }
{ "success": false, "error": { "code": "UNAUTHENTICATED", "message": "A valid device token is required." } }
```

| HTTP | code | Meaning / what the app should do |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Token missing, wrong or revoked. Stop and ask the user to pair again. |
| 401 | `INVALID_PAIRING_CODE` | Code wrong, expired or already used. |
| 403 | `DEVICE_DISABLED` | Admin disabled this device. Stop sending; poll rarely. |
| 404 | `NOT_FOUND` | Unknown endpoint or message id. |
| 409 | `MESSAGE_NOT_ASSIGNED` | Message is not held by this device (expired reservation, cancelled...). Drop it and do **not** send it. |
| 409 | `DEVICE_ALREADY_REGISTERED` | This `device_uuid` is paired as another device. |
| 422 | `INVALID_*` | Bad input. |
| 429 | `RATE_LIMITED` | Slow down. |
| 500 | `SERVER_ERROR` | Retry later with backoff. |

## 4. Endpoints

### POST /register
No token. Body:
```json
{
  "pairing_code": "ABCD-EFGH-JKLM",
  "device_uuid": "3f9c2b1e-7a4d-4e21-9d0b-5c8e2a1f6b90",
  "manufacturer": "Samsung", "model": "Galaxy A15", "android_version": "14",
  "app_version": "1.0.0", "phone_number": "03001234567", "sim_slot": 1, "sim_name": "Jazz"
}
```
`device_uuid` is required: a stable random id the app generates once (letters, digits, `. _ : -`, up to 100 characters).
Response `201`:
```json
{
  "success": true,
  "token": "smsgw_5e80...b869f",
  "token_type": "Bearer",
  "device": { "id": 3, "device_uuid": "3f9c...", "device_name": "Office Samsung", "phone_number": "+923001234567", "sim_slot": 1 },
  "config": { "...": "same object as GET /config" }
}
```

### POST /heartbeat
Body (all optional):
```json
{ "battery_level": 80, "network": "wifi", "sim_slot": 1, "phone_number": "+923001234567", "app_version": "1.0.0" }
```
Response:
```json
{ "success": true, "server_time": "2026-09-27T10:10:00+05:00", "queued_messages": 12, "held_by_device": 0, "next_heartbeat_seconds": 60 }
```
The device shows **Connected** while heartbeats arrive, and **Offline** after
`offline_timeout_minutes` (default 5) without one. No cron is needed for this.

### POST /device-info
Same body as heartbeat plus `manufacturer`, `model`, `android_version`, `sim_name`. Use it after an app update or a SIM change. Returns `{ "device": {...} }`.

### GET /config
```json
{
  "success": true,
  "config": {
    "api_version": "1", "sms_enabled": true, "queue_processing_enabled": true,
    "batch_size": 10, "heartbeat_interval_seconds": 60, "poll_interval_seconds": 60,
    "reservation_timeout_minutes": 10, "default_sim_slot": null,
    "quiet_hours": { "start": "21:00", "end": "07:00" },
    "retryable_error_codes": ["NO_SIGNAL", "DEVICE_TEMPORARILY_UNAVAILABLE", "SIM_BUSY", "NETWORK_ERROR", "RADIO_OFF", "GENERIC_FAILURE", "TIMEOUT"],
    "server_time": "2026-09-27T10:10:00+05:00"
  }
}
```
`quiet_hours` is `null` when disabled. During quiet hours, `/messages` returns an empty list.

### GET /messages?limit=10
`limit` is 1–100; the server also caps it at `batch_size`. Response:
```json
{
  "success": true,
  "messages": [
    { "id": 152, "phone_number": "+923001234567", "message": "Ali was absent today. - Arete", "priority": "normal", "sim_slot": 1 }
  ]
}
```
* Only these fields are sent. No student profiles, fee records or other LMS data.
* `phone_number` is always E.164 (`+<country><number>`). `sim_slot` is null for "phone default".
* Ordering: `high` → `normal` → `low`, then oldest first. Scheduled messages appear only once their time has passed.
* **Reservation:** returned messages are locked to this device in one transaction
  (`SELECT … FOR UPDATE SKIP LOCKED` + a status-guarded `UPDATE`). Two devices polling at
  the same moment never get the same message.
* If the device does not report within `reservation_timeout_minutes` (default 10), a
  message it never started (`reserved`) goes back to the queue for any device. A message
  it started (`processing`) is marked **failed** with `GATEWAY_TIMEOUT` rather than resent,
  because it may already have been delivered. An admin can retry it.

### POST /messages/{id}/processing
Call just before handing the SMS to `SmsManager`. Body: none. Response `{ "id": 152, "status": "processing" }`.

### POST /messages/{id}/sent
```json
{ "sent_at": "2026-09-27T10:10:00+05:00", "sim_slot": 1, "android_message_reference": "abc123" }
```
All fields are optional. `sent_at` defaults to the server time. Report "sent" from the `SmsManager` sent-intent
(`RESULT_OK`); for multipart SMS, when every part has succeeded. Repeating the call is harmless.

### POST /messages/{id}/failed
```json
{ "error_code": "NO_SIGNAL", "error_message": "Mobile network unavailable" }
```
Response: `{ "id": 152, "status": "queued", "will_retry": true }` or `{ "status": "failed", "will_retry": false }`.

| `error_code` | Retried? | Map from |
|---|---|---|
| `NO_SIGNAL` | yes | `RESULT_ERROR_NO_SERVICE` |
| `RADIO_OFF` | yes | `RESULT_ERROR_RADIO_OFF` (airplane mode) |
| `GENERIC_FAILURE` | yes | `RESULT_ERROR_GENERIC_FAILURE` |
| `NETWORK_ERROR` | yes | other transient network errors |
| `SIM_BUSY` | yes | SIM busy |
| `DEVICE_TEMPORARILY_UNAVAILABLE` | yes | app-level pause |
| `TIMEOUT` | yes | no sent-intent within your own timeout |
| `INVALID_NUMBER` | no | number rejected |
| `SMS_PERMISSION_DENIED` | no | `SEND_SMS` permission missing |
| `SIM_NOT_AVAILABLE` | no | no SIM / slot missing |
| anything else | no | stored as given (A–Z, 0–9, _; up to 40 characters) |

**Retry policy:** a retryable failure goes back to `queued` with `retry_count + 1`, and becomes
available again after `retry_delay_minutes × attempt` (default 5, 10, 15 minutes). After
`max_retries` (default 3) it becomes `failed`. Non-retryable codes fail immediately. There are
no infinite loops. Admins can manually **Retry** failed messages, which resets the count.

### POST /messages/bulk-status
Up to 100 results in one call:
```json
{ "results": [
  { "id": 152, "status": "sent", "sent_at": "2026-09-27T10:10:00+05:00", "sim_slot": 1, "android_message_reference": "abc" },
  { "id": 153, "status": "failed", "error_code": "NO_SIGNAL", "error_message": "No service" },
  { "id": 154, "status": "processing" }
] }
```
Response: `{ "results": [ { "id": 152, "success": true, "status": "sent" }, { "id": 153, "success": true, "status": "queued" }, { "id": 999, "success": false, "error": { "code": "MESSAGE_NOT_ASSIGNED", "message": "..." } } ] }`

## 5. Message statuses

| Status | Meaning |
|---|---|
| `scheduled` | Waiting for `scheduled_at`; then treated like `queued`. |
| `queued` | Waiting for a gateway (also after a retryable failure, once the delay passes). |
| `reserved` | Handed to one device by `/messages`. |
| `processing` | Device reported it is sending. |
| `sent` | Device confirmed it was sent. **Only the device sets this.** |
| `failed` | Permanent failure, out of retries, or gateway timeout. |
| `cancelled` | Cancelled by an admin, or an absence alert cancelled because attendance was corrected. |

## 6. Suggested app behaviour

* Run as a foreground service with a persistent notification, and ask the user to exempt it from battery optimisation.
* Send one SMS at a time, with a small delay (1–3 s) between messages; carriers throttle bursts.
* Use `SmsManager.divideMessage` + `sendMultipartTextMessage` for long or Urdu text.
* Use the SIM from `sim_slot` via `SubscriptionManager` when set; otherwise the default SMS SIM.
* Persist the id of each in-flight message so a crash does not resend it. If the app restarts
  with a message stuck in `processing`, report it as `sent` or `failed`, never send it again.
* Back off exponentially on network errors; stop on `401` / `403`.

## 7. Example (curl)

```bash
BASE=https://lms.example.com/api/sms-gateway/v1
curl -s -X POST $BASE/register -H 'Content-Type: application/json' \
  -d '{"pairing_code":"ABCD-EFGH-JKLM","device_uuid":"test-device-1","model":"Pixel 7"}'
TOKEN=smsgw_...
curl -s -X POST $BASE/heartbeat -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"battery_level":90}'
curl -s "$BASE/messages?limit=5" -H "Authorization: Bearer $TOKEN"
curl -s -X POST $BASE/messages/152/processing -H "Authorization: Bearer $TOKEN"
curl -s -X POST $BASE/messages/152/sent -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"sim_slot":1}'
```

## 8. Security checklist

- HTTPS only in production (`APP_URL` must be `https://…`).
- Store the token encrypted on the phone; never log it.
- Pairing codes expire after 30 minutes and work once.
- The API returns only the id, number, text, priority and SIM of each message.
- Errors never include SQL, file paths or stack traces.
- Every state change is written to `sms_logs` with the device as the actor.
