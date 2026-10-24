# Backup and restore

## Backup

Back up both the MySQL database and user uploads. Run backups from a restricted service account, encrypt them in transit and at rest, retain multiple generations, and copy at least one generation off-host.

Example logical database backup:

```sh
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 -h DB_HOST -u BACKUP_USER -p school_app > school_app_YYYYMMDD_HHMMSS.sql
```

Archive `public/uploads`, `storage/uploads` and the matching private `.env` (including the original APP_KEY) at the same logical point in time. Include Android signing keys if maintaining the companion app, and each tenant's database/environment/uploads if using the multi-school platform. See [the new computer guide](NEW-COMPUTER.md). Record the release version, migration list, database checksum, upload checksum, and backup timestamp. Automate daily backups and perform a restore drill at least quarterly; an untested backup is not considered recoverable.

## Restore

1. Stop application writes and preserve the damaged environment for investigation.
2. Provision an empty database with the same charset/collation and a clean upload directory.
3. Verify backup checksums, decrypt, and import the SQL into the empty database.
4. Restore the matching uploads archive without overwriting unrelated files.
5. Configure a non-public validation instance and run `php database/migrate.php` if restoring into a newer application release. Run `php tests/run.php` against a separate disposable database only, because feature tests write records.
6. Verify row counts for users, students, employees, financial records, attendance, marks, messages, and migrations. Verify a sample of upload paths.
7. Switch traffic only after validation; rotate any credentials suspected of exposure.

Never test a restore by overwriting the only production database.

