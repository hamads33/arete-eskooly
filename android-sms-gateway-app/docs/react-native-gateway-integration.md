# React Native gateway ↔ LMS API integration

The app implements the contract in the LMS repo's `docs/android-sms-gateway.md` (API v1),
cross-checked against `app/Controllers/SmsGatewayApiController.php`,
`app/Services/Sms/SmsGateway.php` and `app/Services/Sms/SmsQueue.php`.
Where the generic app spec and the real backend disagreed, the backend won.

API base: `<LMS URL>/api/sms-gateway/v1`. All calls: `src/api/client.ts`.

## Endpoint mapping

| App action | Endpoint | When | Code |
|---|---|---|---|
| Test Connection | `GET /config` without a token; the API's own `401 UNAUTHENTICATED` JSON proves the URL is right | Pair screen | `testConnection()` |
| Pair Device (code) | `POST /register` `{pairing_code, device_uuid, manufacturer, model, android_version, app_version, sim_slot?, sim_name?, phone_number?}` → `token`, `device`, `config` | Pair screen | `registerDevice()` |
| Pair Device (token) | `GET /config` with the pasted `smsgw_…` token, then `POST /device-info` | Pair screen, "Device token" tab | `pairWithToken()` |
| Heartbeat | `POST /heartbeat` `{battery_level, network, app_version}` | every `heartbeat_interval_seconds` while running | `heartbeat()` in `gatewayEngine.ts` |
| SIM changed | `POST /device-info` `{sim_slot, sim_name, phone_number?, manufacturer, model, android_version, …}` | once after the selected SIM changes | same |
| Config | `GET /config` | on pairing, every 10 min while running, and on "Sync Now" | `refreshConfig()` |
| Fetch Queue | `GET /messages?limit=<batch_size>` | each cycle, only when the local backlog is empty | `processQueue()` |
| Processing | `POST /messages/{id}/processing` | right before handing the SMS to Android | `processJob()` |
| Sent | `POST /messages/{id}/sent` `{sent_at, sim_slot, android_message_reference}` | right after Android's sent-intent says OK (all parts) | `ackOne()` |
| Failed | `POST /messages/{id}/failed` `{error_code, error_message}` | right after Android reports failure | `ackOne()` |
| Pending acks | `POST /messages/bulk-status` (≤100 per call) | start of every cycle, and every ~15 min from WorkManager while stopped | `flushAcks()` |

Auth: `Authorization: Bearer <token>` on every call except `/register`. Responses are
checked for `success: true`; errors are classified by HTTP status and `error.code`
(`src/api/errors.ts`).

| LMS answer | App behaviour |
|---|---|
| 401 `UNAUTHENTICATED` | Connection → `AUTH_ERROR`, gateway service stopped, no more fetching/sending. Pending acks stay in SQLite. User must pair again. |
| 403 `DEVICE_DISABLED` | Connection → `DEVICE_DISABLED`, gateway stopped. Background ack retries continue at WorkManager pace (~15 min). |
| 409 `MESSAGE_NOT_ASSIGNED` on `/processing` | Job → `CANCELLED`, **not sent**. |
| 409 on `/sent` | Job → `ACK_REJECTED` (SMS did go out; the LMS had already given up on it). Not retried. |
| 409 on `/failed` | Job → `FAILED_COMPLETE` (the LMS already closed it, e.g. a repeated report). |
| 429 | Nothing is replayed; the next cycle continues. |
| 5xx / network / timeout | Connection → `SERVER_OFFLINE`; sent/failed results wait as `ACK_PENDING` / `FAILURE_ACK_PENDING` with exponential backoff (30 s → 30 min). |

## Error-code mapping (Android → LMS `error_code`)

| Android (`SmsManager` result) | App code | LMS `error_code` | LMS retries? |
|---|---|---|---|
| `RESULT_ERROR_NO_SERVICE` | `NO_SERVICE` | `NO_SIGNAL` | yes |
| `RESULT_ERROR_RADIO_OFF`, `RESULT_RADIO_NOT_AVAILABLE` | `RADIO_OFF` | `RADIO_OFF` | yes |
| `RESULT_ERROR_GENERIC_FAILURE` (and unknown codes) | `GENERIC_FAILURE` | `GENERIC_FAILURE` | yes |
| `RESULT_NETWORK_REJECT`, `RESULT_NETWORK_ERROR`, RIL network codes | `NETWORK_ERROR` | `NETWORK_ERROR` | yes |
| `RESULT_ERROR_LIMIT_EXCEEDED`, `RESULT_RIL_REQUEST_RATE_LIMITED` | `SMS_LIMIT_EXCEEDED` | `DEVICE_TEMPORARILY_UNAVAILABLE` | yes (app also pauses 15 min) |
| `RESULT_ERROR_FDN_CHECK_FAILURE` | `FDN_CHECK_FAILED` | `FDN_CHECK_FAILED` | no |
| `RESULT_ERROR_NULL_PDU` | `NULL_PDU` | `NULL_PDU` | no |
| invalid arguments / SMSC | `INVALID_NUMBER` | `INVALID_NUMBER` | no |
| short code blocked | `SHORT_CODE_BLOCKED` | `SHORT_CODE_BLOCKED` | no |
| SIM gone / permission missing **before** Android accepted the SMS | `SIM_UNAVAILABLE` / `PERMISSION_DENIED` | `DEVICE_TEMPORARILY_UNAVAILABLE` | yes |
| no sent-intent within 3 min | `RESULT_UNKNOWN` | `SEND_RESULT_UNKNOWN` | no |

## Mismatches and decisions

1. **Pairing input.** The backend pairs with a one-time `XXXX-XXXX-XXXX` code, not a device
   name. The app's "Device Name" is a local label; the LMS keeps the name the admin gave the
   device (the app shows the LMS name after pairing). Admin-issued tokens ("Generate Token")
   are supported through the "Device token" tab.
2. **`X-Device-Token` is not sent.** The docs allow it when a proxy strips `Authorization`.
   Android's HTTP stack drops `Authorization` on a cross-host redirect but would forward a
   custom header, so the app sends only `Authorization`. If a proxy strips it, fix the proxy.
3. **Config fields not provided by the backend:** `gateway_enabled`, `sms_delay`,
   `daily_limit`, `max_retries`, `offline_timeout`. The app treats
   `sms_enabled && queue_processing_enabled` as "gateway enabled"; SMS delay (default 3 s) and
   an optional daily limit are local settings. Retries are the server's job (`retry_count`,
   `max_retries`), so the app never retries an SMS by itself.
4. **Poll interval.** The backend derives `poll_interval_seconds` from the heartbeat interval
   (clamped 15–120 s). The app uses `max(local sync interval, poll_interval_seconds)`.
5. **`sim_slot` in heartbeats overwrites the device's slot on the server** (`recordInfo`).
   Sending it every minute would undo an admin's slot choice made in the LMS, so the app
   reports the SIM only via `/device-info` when the selected SIM actually changes.
6. **Per-message `sim_slot`.** The server sends the device's slot (or the institute default),
   1-based. The app uses that slot when an active SIM sits in it, otherwise the SIM chosen on
   the phone.
7. **Processing is confirmed late on purpose.** The backend fails any message left in
   `processing` past the reservation timeout with `GATEWAY_TIMEOUT` (never resent). The app
   therefore validates permission and SIM *before* calling `/processing`, and calls it
   immediately before sending. Messages reserved while the phone cannot send are simply not
   touched and return to the queue after `reservation_timeout_minutes`.
8. **Reserved messages are not returned by `/messages` again.** The app keeps them in SQLite
   and works off that local backlog before asking for more.
9. **Timeout code.** The docs suggest `TIMEOUT` (retryable) for a missing sent-intent. Because
   the SMS might still have gone out, the app reports `SEND_RESULT_UNKNOWN` (not retryable)
   so the LMS never auto-resends it; an admin can retry it deliberately.
10. **Late `sent` after `GATEWAY_TIMEOUT`.** If the LMS already failed a message and the
    phone later confirms it was sent, `/sent` returns 409. The app records `ACK_REJECTED` and
    stops retrying. *Backend suggestion:* accept a late `sent` for `GATEWAY_TIMEOUT` failures
    so the LMS history is accurate.
11. **Delivery reports** have no endpoint; they are shown on the phone (Jobs tab) only.
12. **Heartbeat only while running.** A stopped gateway does not heartbeat, so the LMS shows
    it Offline after `offline_timeout_minutes`, which is the truth (it is not sending).
