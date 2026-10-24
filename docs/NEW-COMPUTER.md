# Working on another computer

GitHub contains the application source, assets, migrations, Composer lockfile, Android source,
npm lockfile and Gradle wrapper. Dependencies are installed from the lockfiles; no frontend
build is needed for the PHP website.

## Start with an empty development database

Install Git, PHP 8.2+ CLI, Composer 2 and Docker with Compose. Enable PHP extensions
pdo_mysql, mbstring, gd, openssl, fileinfo, dom, curl and zip. Run these from a terminal:

```powershell
git clone https://github.com/hamads33/arete-eskooly.git
cd arete-eskooly
php scripts/setup-local.php
composer install
docker compose up -d mysql
docker compose ps
```

Wait until MySQL is healthy. Edit the new `.env`: set `DEV_ADMIN_EMAIL` and a unique
`DEV_ADMIN_PASSWORD` of at least 12 characters. Then run:

```powershell
php database/migrate.php
php database/seed.php
php -S 127.0.0.1:8081 -t public public/router.php
```

Open http://127.0.0.1:8081/login and sign in with the account you configured.
The example password matches the local Docker service. Use different credentials for deployment.
Port 3307 must be free; if changed, update both Compose and `.env`. The named Docker volume
is created automatically. Never run `docker compose down -v` if you need to keep its data.
The setup script preserves an existing `.env`; it creates a random APP_KEY only for a new one.

## Continue with the SAME school data

A clone starts with code, not the current school records. Transfer these privately:

| Item | Why it is needed |
| --- | --- |
| MySQL database dump | Users, students, fees, exams, settings, integration tokens and migration state |
| `.env` | Original APP_KEY, integration settings and local configuration |
| `public/uploads/` | Logos, photos and public attachments |
| `storage/uploads/` | Private attachments, question papers and uploaded content |
| Android `android/keystore.properties` and its referenced `.jks`/keystore | Sign updates accepted by phones with the existing release APK |
| Android `android/app/debug.keystore`, if reusing installed debug APKs | Preserve debug signing identity |

Keep the original APP_KEY with the database: changing it can make encrypted credentials
unreadable. Adjust DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD and APP_URL for the new machine.
Do not point a development checkout at a live database or enable real SMS/automation accidentally.
Do not seed over a restored database; existing logins remain valid. Import into a separate empty
database, restore the matching uploads, then run migrations for any newer code.
See [backup and restore](BACKUP-RESTORE.md) for the dump/import procedure.

For the multi-school platform, also transfer the registry database, every `school_*` database,
and each school's `.env` and uploads. Server provisioning requires the Linux services described
in [multi-school setup](multi-school.md); a Windows checkout does not reproduce that server.

Sessions, caches, logs, vendor, node_modules, APK build output and generated financial reports
are not needed to develop the application. Dependencies and caches can be recreated.
Keep any financial exports you want to retain as separate private documents.
GitHub is not a backup of this private runtime data.

## Android companion (optional for website development)

Install the Node version required by `package.json` and the locked React Native package,
JDK and Android SDK described in the [Android README](../android-sms-gateway-app/README.md).
Use a short checkout path on Windows, such as `C:\src\arete-eskooly`, for native build limits.

```powershell
cd android-sms-gateway-app
npm ci
npm run typecheck
npm test -- --runInBand
cd android
.\gradlew.bat assembleDebug
```

Set ANDROID_HOME or create ignored `android/local.properties` with your own SDK path.
A new debug build uses the standard workstation debug key if no project debug key is present.
Release builds need the original private signing key to update an existing installation.
See the Android README for Metro, phone pairing and release commands.

## Validation

See [the recorded verification results and known failures](PORTABILITY-VERIFICATION.md).

`composer check-platform-reqs` checks installed PHP dependencies.
Run `php tests/run.php` only against a disposable test database: feature tests write records.
Verify login, dashboard, a fee voucher PDF and uploaded attachments after restoring private data.
Google Meet, SMS and production host configuration require their original credentials and services.
Optional MCP browser tooling needs Node/npm and Chrome; it is not required to run the website.
