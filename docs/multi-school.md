# School sign-up platform

Other schools register at `https://portal.aretecoachingcenter.com/register-school`. Arete's
administrators approve them in the portal admin at `/admin/schools` (the dashboard shows a notice when
requests are waiting), and approval builds the school automatically:

1. a MariaDB database `school_<slug>` and its own user (random password)
2. a site folder `/var/www/schools/<slug>` holding the school's `.env`, `storage/` (sessions, logs,
   private uploads) and `public/uploads/`; the code folders are symlinks to the portal's `current`
   release, so every deploy updates all schools at once
3. all migrations, then the school's name/contact details and its administrator (the password the
   school chose at sign-up)

The school is then live at `https://<slug>.aretecoachingcenter.com`.

The main Arete portal is not affected: it keeps its own `.env`, database, uploads and branding,
and the platform routes exist only when `PLATFORM_ENABLED=true` is in its `.env`. School sites
never load the platform routes (`SCHOOL_SLUG` is set in their `.env`).

## Server setup (done 2026-10-11)

- **DNS:** Cloudflare `A *` -> 13.206.45.239, proxied. Cloudflare SSL mode is **Full**, so the origin
  uses a self-signed wildcard certificate in `/etc/ssl/schools/` (10 years). If the mode is ever
  changed to Full (strict), replace it with a Cloudflare Origin Certificate first.
- **nginx:** `/etc/nginx/sites-available/schools.aretecoachingcenter.com` (regex server for
  `<slug>.aretecoachingcenter.com`, root `/var/www/schools/$school/public`, 404 for unknown schools).
  Exact server_names (portal, www, other sites) always win over it.
- **Folders:** `/var/www/schools` owned by www-data.
- **MariaDB:** account `eskooly_provisioner` (CREATE, CREATE USER globally; ALL on `school\_%` and
  `eskooly_platform`). MariaDB will not let it pass grants on from the wildcard, so the root-owned
  procedure `eskooly_platform.provision_school(db, user, password)` creates each school's database and
  user; it only accepts `school_*` / `sch_*` names.
- **Portal `.env`:** `PLATFORM_ENABLED`, `PLATFORM_DOMAIN`, `PLATFORM_CODE_PATH` (the `current`
  symlink), `PLATFORM_SCHOOLS_PATH`, `PLATFORM_PHP_BINARY`, `PLATFORM_DB_*`, `PLATFORM_SCHOOL_DB_USER_HOST`.
- **Cron:** `/etc/cron.d/eskooly-schools` runs the portal's four daily scripts for every school.

## Every deploy

After switching `current` and reloading FPM, also migrate the schools:

```
sudo -u www-data php /var/www/portal.aretecoachingcenter.com/current/scripts/each_school.php database/migrate.php
```

## Per-school notes

- **Suspend / Resume** in `/platform` drops a `SUSPENDED` file: the site answers 503, data is kept.
- **SMS:** off until the school pairs its own Android gateway phone in its SMS Center.
- **Google Meet:** not configured for new schools (each subdomain's redirect URI must be added in
  Google Cloud Console and `GOOGLE_*` set in that school's `.env`).
- **Backups:** include every `school_%` database and `/var/www/schools` in the backup job.
