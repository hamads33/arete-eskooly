# Arete SMS Gateway (Android)

A private Android companion app that turns a phone with the institute's SIM into the LMS's SMS
gateway. It is **not** an LMS mobile app: it only fetches SMS the LMS queued, sends them from
the chosen SIM, and reports the result.

```
LMS web app → HTTPS API (/api/sms-gateway/v1) → React Native (TypeScript) → Kotlin TurboModule
          → Android SmsManager → SIM → parent / student
```

Backend contract: `../docs/android-sms-gateway.md` in the LMS repo.
Endpoint mapping and mismatches: [`docs/react-native-gateway-integration.md`](docs/react-native-gateway-integration.md).

## Stack

React Native 0.87 (bare CLI, New Architecture, Hermes) · TypeScript · React Navigation
(native stack) · Zustand · Axios · op-sqlite (jobs + logs) · react-native-keychain (token,
device UUID; Android Keystore AES-GCM) · AsyncStorage (non-sensitive prefs only) · Kotlin
TurboModule + foreground service + WorkManager. No Expo, no Firebase, no analytics.

## Structure

```
index.js                     registers the UI and the two headless tasks
App.tsx
src/
  api/          client.ts (typed LMS API), errors.ts (error classification)
  native/       NativeSmsGateway.ts (codegen spec = JS↔Kotlin contract), SmsGateway.ts
  services/     gatewayEngine.ts (sync cycle, state machine), gatewayController.ts (pair/start/stop),
                smsBridge.ts (send + await native verdict), simService.ts, permissions.ts,
                testSms.ts, logger.ts, events.ts
  storage/      database.ts (SQLite schema), jobRepository.ts, logRepository.ts,
                secureStore.ts (Keychain), prefs.ts (AsyncStorage)
  store/        gatewayStore.ts (Zustand UI read-model)
  screens/      Splash, Pair, Permissions, SimSelection, Dashboard, TestSms, Logs, Settings, About
  navigation/ components/ hooks/ types/ utils/
android/app/src/main/java/com/arete/smsgateway/sms/
  SmsGatewayModule.kt        TurboModule "SmsGateway"
  SmsGatewayPackage.kt
  SmsSender.kt               SmsManager single/multipart, unique PendingIntents
  SmsSentReceiver.kt         per-segment sent callbacks → one logical result
  SmsDeliveredReceiver.kt    carrier delivery reports (informational)
  SmsResultStore.kt          durable native record of every send attempt
  SmsErrorMapper.kt          result codes → clean error codes
  SimDirectory.kt            SubscriptionManager SIM discovery, per-SIM SmsManager
  DeviceStatus.kt            battery / network for heartbeats
  GatewayForegroundService.kt  keeps the gateway alive, runs the JS "GatewayTick" task
  HeadlessTaskRunner.kt      starts React Native headless tasks without the UI
  MaintenanceWorker.kt       WorkManager: ack retries while the gateway is stopped
  BootReceiver.kt            restart after reboot / app update (if enabled and it was running)
  GatewayPrefs.kt  SmsEvents.kt
```

## How it works

**One cycle** (`runCycle` in `gatewayEngine.ts`, serialized by a mutex):

1. Settle any job stuck in `SENDING` from the native result store (app was killed mid-send).
2. Deliver pending sent/failed acknowledgments (`bulk-status`).
3. Refresh config (every 10 min), heartbeat (every `heartbeat_interval_seconds`).
4. Pre-flight: LMS sending enabled, SMS permission, selected SIM present, local daily limit.
5. Work off local backlog, else `GET /messages?limit=batch_size`; for each job, sequentially:
   `/processing` → persist `SENDING` → native send → wait for Android's sent-intent for every
   part → persist `SENT_LOCAL` / `FAILED_LOCAL` → `/sent` or `/failed` → `COMPLETED` /
   `FAILED_COMPLETE`, or `ACK_PENDING` / `FAILURE_ACK_PENDING` if the LMS is unreachable.
   A configurable delay (default 3 s) separates messages.

**Who runs the cycle:** while the gateway is on, `GatewayForegroundService` (type
`specialUse`, silent minimal notification, partial wake lock) runs the `GatewayTick` headless task
every sync interval, starting the React Native runtime itself if the UI is closed; the next
tick is scheduled only after the previous one finished. While the gateway is off,
`MaintenanceWorker` (WorkManager, ~15 min, needs network) runs `GatewayMaintenance`, which only
delivers acknowledgments and never sends SMS. "Sync Now" runs a cycle directly.

### Job states

```
RECEIVED → PROCESSING → SENDING ─┬→ SENT_LOCAL ─┬→ COMPLETED
                                 │              ├→ ACK_PENDING ──→ COMPLETED
                                 │              └→ ACK_REJECTED   (LMS refused a late confirmation)
                                 └→ FAILED_LOCAL ─┬→ FAILED_COMPLETE
                                                  └→ FAILURE_ACK_PENDING → FAILED_COMPLETE
PROCESSING → CANCELLED            (LMS says the message is no longer ours; not sent)
FAILED_COMPLETE → RECEIVED        (LMS re-queued a retryable failure: new attempt)
```

### Duplicate prevention

* The LMS message id is the SQLite primary key (`server_message_id UNIQUE`).
* `SENDING`, `SENT_LOCAL`, `ACK_PENDING`, `COMPLETED`, `ACK_REJECTED` can never be handed to
  `SmsManager` again, whatever the server returns (`jobRepository.receive`).
* `SENDING` is committed to SQLite *before* the native call. The native module writes its own
  record (`SmsResultStore`, `commit()`) *before* calling `SmsManager`, and updates it from the
  sent-intent broadcast even if JS is dead. On restart a `SENDING` job is settled from that
  record; no record means Android never got it.
* All state changes are status-guarded `UPDATE … WHERE status IN (…)`.
* Network retries exist only for API calls (idempotent ones); SMS sending is never retried by
  the app. Retries are the LMS's decision (it re-queues retryable failures).
* Native events carry `jobId` and a per-attempt `sendToken`; stale callbacks from an older
  attempt are ignored. There is no global "current message".

## Native event contract

`SmsGateway.onSmsEvent` payload (every event has `jobId`):

```json
{ "jobId": "152", "sendToken": "c1f…", "status": "sending|sent|failed|delivered|delivery_failed",
  "errorCode": null | "NO_SERVICE" | "RADIO_OFF" | "GENERIC_FAILURE" | "SMS_LIMIT_EXCEEDED" | "FDN_CHECK_FAILED" | "SIM_UNAVAILABLE" | "PERMISSION_DENIED" | "NULL_PDU" | "INVALID_NUMBER" | "NETWORK_ERROR" | …,
  "resultCode": -1, "segmentIndex": -1, "segmentCount": 2, "timestamp": 1790000000000 }
```

`sent` is emitted once, only after **every** segment's sent-intent returned `RESULT_OK`;
the first failing segment emits one `failed`. `sendSms()` itself only resolves with
`{jobId, sendToken, segmentCount}` when Android accepted the request, and rejects with a code
(`PERMISSION_DENIED`, `SIM_UNAVAILABLE`, `INVALID_NUMBER`, …) when it did not.

## Permissions

`SEND_SMS`, `READ_PHONE_STATE` (SIM list for SIM 1/SIM 2 — required by `SubscriptionManager`),
`INTERNET`, `ACCESS_NETWORK_STATE`, `FOREGROUND_SERVICE`, `FOREGROUND_SERVICE_SPECIAL_USE`,
`WAKE_LOCK`, `RECEIVE_BOOT_COMPLETED`,
`REQUEST_IGNORE_BATTERY_OPTIMIZATIONS`. Not requested: `READ_SMS`, `RECEIVE_SMS`,
`READ_CONTACTS`, `READ_PHONE_NUMBERS`, `POST_NOTIFICATIONS`, IMEI/serial access.

Android requires a notification for a service that keeps running. The app uses a silent
minimum-importance channel ("Background service", no status-bar icon) with a fixed
"Running in background" line, and never requests notification permission, so on Android 13+
the notification is not shown in the shade at all. (The SIM's own number is shown
when Android provides it without extra permissions; often it is not.)

The foreground service uses type `specialUse` because `dataSync` is time-limited on Android 15
and cannot start from `BOOT_COMPLETED`. Fine for a privately distributed app; a Play Store
listing would need a special-use justification.

## Security

* Token in Android Keystore (Keychain `AES_GCM_NO_AUTH`); never in AsyncStorage, state, logs
  or the console; the token field is a password field and is cleared after pairing.
* Release: HTTPS only, system CAs only (`res/xml/network_security_config.xml`). Debug builds
  additionally allow `http://` and user-installed CAs (`src/debug/…`) for LAN testing. There
  is no trust-all code anywhere.
* Backups/device transfer excluded (`allowBackup=false`, `data_extraction_rules.xml`).
* Logs mask numbers (`+92300****567`) and never contain message text.

## Build

Requirements: Node 22.13+ (22.x), 24.3+ (24.x), or 26+, JDK 17–21, Android SDK (Gradle downloads platform 37 / build-tools
/ NDK on first build if licences are accepted).

```bash
npm ci
npm run typecheck && npm test
cd android
./gradlew assembleDebug        # Windows: gradlew.bat assembleDebug
```

Debug APK: `android/app/build/outputs/apk/debug/app-debug.apk`
(debug APKs load JS from Metro: run `npm start` and `adb reverse tcp:8081 tcp:8081`, or use
the release build for a phone that runs on its own).

Install on a connected phone: `adb install -r android/app/build/outputs/apk/debug/app-debug.apk`

### Release build

1. Generate a keystore once and keep it (and its passwords) safe, outside git:
   ```bash
   keytool -genkeypair -v -storetype PKCS12 -keystore arete-smsgw.jks -alias smsgw \
     -keyalg RSA -keysize 4096 -validity 10000
   ```
2. Create `android/keystore.properties` (git-ignored):
   ```properties
   storeFile=C:/secure/arete-smsgw.jks
   storePassword=…
   keyAlias=smsgw
   keyPassword=…
   ```
   or set `SMSGW_STORE_FILE`, `SMSGW_STORE_PASSWORD`, `SMSGW_KEY_ALIAS`, `SMSGW_KEY_PASSWORD`.
3. Build:
   ```bash
   cd android && ./gradlew assembleRelease -PreactNativeArchitectures=arm64-v8a,armeabi-v7a
   ```
   **Windows:** the native (C++) build hits the 260-character path limit when the project sits
   in a deep folder. Map the project to a drive letter and build from there (no admin needed):
   ```bat
   subst S: C:\Users\<you>\Desktop\arete-eskooly\android-sms-gateway-app
   S:
   cd android
   gradlew.bat assembleRelease -PreactNativeArchitectures=arm64-v8a,armeabi-v7a
   ```
   (`metro.config.js` already handles bundling from a `subst` drive. `subst S: /D` removes it.)
   Output: `android/app/build/outputs/apk/release/app-release.apk` (signed when step 2 is
   done; `app-release-unsigned.apk` otherwise). Bump `versionCode`/`versionName` in
   `android/app/build.gradle` for each release.

## Pairing with the LMS

1. LMS: **SMS Center → Gateway Devices → Register Device**. Note the base URL and the one-time
   pairing code (`XXXX-XXXX-XXXX`, valid 30 min).
2. Phone: install the APK, open it, enter the LMS URL (e.g. `https://school.example.com`),
   tap **Test Connection**, enter the pairing code, tap **Connect Gateway**.
   (Alternatively paste a `smsgw_…` token from **Generate Token** on the "Device token" tab.)
3. Grant **Send SMS** and **Phone state**, allow notifications and background running.
4. Choose the SIM (SIM 1 / SIM 2).
5. Dashboard → **Test SMS** to your own phone, then **START GATEWAY**.
6. Enable **Start gateway after device reboot** in Settings if the phone should resume on its own.

Keep the phone on a charger, with mobile signal and Wi-Fi/data.

## Tests

`npm test` runs the engine and repositories against real SQLite (Node's `node:sqlite`) with the
Kotlin module and HTTP faked: send-once-after-callback, ACK_PENDING after an outage and
ack-only retry, duplicate server jobs, restart with `SENDING` (sent / never reached Android),
failure reporting and batch stop, FAILURE_ACK_PENDING, re-queued retry as a new attempt, SIM
rejected before sending, 401 stop, SIM removed, stopped-gateway maintenance, late-ack 409.

### On-device checklist (needs real SIMs)

- [ ] single-SIM and dual-SIM phone; SIM 1 and SIM 2 each send
- [ ] remove / disable / swap the selected SIM → "The selected SIM is no longer available", sending paused
- [ ] permission granted / denied / "don't ask again" → App Settings
- [ ] English, Urdu, long (multipart) SMS arrive intact
- [ ] airplane mode / no signal → RADIO_OFF / NO_SIGNAL reported, LMS re-queues
- [ ] Wi-Fi off after an SMS went out → ACK_PENDING, synced when back, no second SMS
- [ ] LMS down → "LMS server is currently unreachable", acks catch up later
- [ ] revoke token / disable device in LMS → gateway stops with a specific message
- [ ] kill the app mid-batch, reopen → nothing is sent twice
- [ ] reboot with "Start after reboot" on/off; stopped gateway stays stopped
- [ ] large queue (100+) paces at the configured delay; SMS limit prompt pauses sending
