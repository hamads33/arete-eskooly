<?php

declare(strict_types=1);

namespace App\Platform;

use PDO;

/**
 * Turns an approved sign-up into a working school: its own MySQL database and user, its own site
 * folder (.env, uploads, sessions, logs) whose code folders point at the shared release, the
 * schema, and the school's administrator. Nothing here reads or writes the main school's data.
 *
 * Every step can be re-run, so "Retry" after a failure finishes the job instead of duplicating it.
 */
final class SchoolProvisioner
{
    /** Code shared by every school, linked from the site folder to the current release. */
    private const CODE_LINKS = ['app', 'bootstrap', 'config', 'database', 'routes', 'scripts', 'vendor', 'composer.json'];
    private const PUBLIC_LINKS = ['assets', 'offline.html', 'sw.js'];

    public function __construct(private readonly SchoolRegistry $registry) {}

    public static function siteDir(string $slug): string
    {
        return PlatformConfig::schoolsPath() . '/' . $slug;
    }

    public static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9-]{1,28}[a-z0-9]$/', $slug) && !str_contains($slug, '--') && !in_array($slug, PlatformConfig::RESERVED, true);
    }

    /** @return array{0:bool,1:string} success flag and a message for the platform admin */
    public function provision(int $id): array
    {
        $school = $this->registry->find($id);
        if (!$school) return [false, 'School request not found.'];
        if (!in_array($school['status'], ['pending', 'failed'], true)) return [false, 'Only pending or failed requests can be approved.'];
        $slug = (string) $school['slug'];
        if (!self::validSlug($slug)) return [false, 'The subdomain is not allowed.'];

        $dbName = 'school_' . str_replace('-', '_', $slug);
        $dbUser = substr('sch_' . str_replace('-', '_', $slug), 0, 32);
        $dbPassword = bin2hex(random_bytes(18));
        try {
            $this->createDatabase($dbName, $dbUser, $dbPassword);
            $this->registry->update($id, ['db_name' => $dbName, 'db_user' => $dbUser]);
            $site = $this->buildSite($slug, (string) $school['name'], $dbName, $dbUser, $dbPassword);
            $this->migrate($site);
            $this->seedSchool($dbName, $school);
            // The sign-up logo is a nicety: a problem with it never fails the school's setup.
            $logoNote = '';
            try { SchoolLogo::install($slug, $site, $dbName); } catch (\Throwable $e) { $logoNote = ' (the logo could not be applied; the school can upload it in Institute Profile)'; }
            @unlink($site . '/SUSPENDED');
            $this->registry->update($id, ['status' => 'active', 'approved_at' => date('Y-m-d H:i:s'), 'last_error' => null]);
            return [true, $school['name'] . ' is live at ' . PlatformConfig::schoolUrl($slug) . $logoNote];
        } catch (\Throwable $e) {
            $this->registry->update($id, ['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 4000)]);
            return [false, 'Setup failed: ' . $e->getMessage()];
        }
    }

    /** Suspended schools keep all their data; the site just answers "temporarily unavailable". */
    public function suspend(int $id, bool $suspend): void
    {
        $school = $this->registry->find($id);
        if (!$school || !in_array($school['status'], ['active', 'suspended'], true)) return;
        $flag = self::siteDir((string) $school['slug']) . '/SUSPENDED';
        if ($suspend) file_put_contents($flag, date('c')); else @unlink($flag);
        $this->registry->update($id, ['status' => $suspend ? 'suspended' : 'active']);
    }

    /**
     * Permanently deletes a school: its database, its database user, its site folder (settings,
     * uploads, sessions, logs) and its registry entry. Only revoked, failed or rejected schools.
     * The address is freed; nginx then sends visitors to "no portal here".
     *
     * @return array{0:bool,1:string}
     */
    public function destroy(int $id): array
    {
        $school = $this->registry->find($id);
        if (!$school) return [false, 'School not found.'];
        if (!in_array($school['status'], ['suspended', 'failed', 'rejected'], true)) return [false, 'Revoke access first, then delete.'];
        $slug = (string) $school['slug'];
        if (!preg_match('/^[a-z][a-z0-9-]{1,28}[a-z0-9]$/', $slug)) return [false, 'Invalid school address.'];
        // Nothing is dropped until a full copy of the database and the site folder is safely on disk.
        try {
            $saved = $this->backupBeforeDelete($school);
        } catch (\Throwable $e) {
            return [false, 'Nothing was deleted: the safety backup failed (' . $e->getMessage() . ').'];
        }
        try {
            $db = (string) ($school['db_name'] ?? '');
            $user = (string) ($school['db_user'] ?? '');
            if ($db !== '' || $user !== '') {
                $pdo = SchoolRegistry::serverConnection();
                if ($db !== '') {
                    if (!preg_match('/^school_[a-z0-9_]+$/', $db)) throw new \RuntimeException('Refusing to drop unexpected database ' . $db);
                    $pdo->exec("DROP DATABASE IF EXISTS `{$db}`");
                }
                if ($user !== '') {
                    if (!preg_match('/^sch_[a-z0-9_]+$/', $user)) throw new \RuntimeException('Refusing to drop unexpected user ' . $user);
                    $pdo->exec('DROP USER IF EXISTS ' . $pdo->quote($user) . '@' . $pdo->quote(PlatformConfig::get('SCHOOL_DB_USER_HOST', 'localhost')));
                }
            }
            $this->removeSite($slug);
            SchoolLogo::discard($slug);
            $this->registry->delete($id);
            return [true, $school['name'] . ' was deleted and ' . $slug . ' is free again. A full backup was kept: ' . implode(', ', array_map('basename', $saved)) . '.'];
        } catch (\Throwable $e) {
            return [false, 'Delete did not finish: ' . $e->getMessage() . ' It is safe to try again.'];
        }
    }

    /** Where the copies taken before a school is deleted are kept (outside every web root). */
    public static function deletedBackupsPath(): string
    {
        return rtrim(str_replace('\\', '/', PlatformConfig::get('DELETED_BACKUPS_PATH', BASE_PATH . '/storage/school-backups')), '/');
    }

    /**
     * Saves the school's database (mysqldump, gzipped) and its site folder (settings and uploads;
     * links into the shared code are stored as links, not followed). Throws on any failure so the
     * caller deletes nothing.
     *
     * @return list<string> the files written
     */
    private function backupBeforeDelete(array $school): array
    {
        $dir = self::deletedBackupsPath();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new \RuntimeException('cannot create the backup folder');
        $base = $dir . '/' . $school['slug'] . '-' . date('Ymd-His');
        $files = [];

        $db = (string) ($school['db_name'] ?? '');
        if ($db !== '') {
            if (!preg_match('/^school_[a-z0-9_]+$/', $db)) throw new \RuntimeException('unexpected database name');
            $exists = SchoolRegistry::serverConnection()->query('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=' . SchoolRegistry::serverConnection()->quote($db))->fetchColumn();
            if ($exists) {
                $cnf = tempnam(sys_get_temp_dir(), 'dump');
                file_put_contents($cnf, "[client]\nhost=" . PlatformConfig::get('DB_HOST', '127.0.0.1') . "\nport=" . PlatformConfig::get('DB_PORT', '3306')
                    . "\nuser=" . PlatformConfig::get('DB_USERNAME') . "\npassword=\"" . addcslashes(PlatformConfig::get('DB_PASSWORD'), "\"\\") . "\"\n");
                @chmod($cnf, 0600);
                $sql = $base . '-database.sql';
                try {
                    $process = proc_open(['mysqldump', '--defaults-extra-file=' . $cnf, '--single-transaction', '--routines', '--triggers', '--no-tablespaces', $db],
                        [1 => ['file', $sql, 'w'], 2 => ['pipe', 'w']], $pipes);
                    if (!is_resource($process)) throw new \RuntimeException('mysqldump could not start');
                    $err = stream_get_contents($pipes[2]);
                    fclose($pipes[2]);
                    $code = proc_close($process);
                } finally {
                    @unlink($cnf);
                }
                $tail = is_file($sql) ? (string) file_get_contents($sql, false, null, max(0, filesize($sql) - 200)) : '';
                if ($code !== 0 || !str_contains($tail, 'Dump completed')) {
                    @unlink($sql);
                    throw new \RuntimeException('database dump failed: ' . trim(mb_substr((string) $err, 0, 300)));
                }
                $in = fopen($sql, 'rb');
                $out = gzopen($sql . '.gz', 'wb6');
                while (!feof($in)) gzwrite($out, (string) fread($in, 1 << 20));
                fclose($in);
                gzclose($out);
                unlink($sql);
                @chmod($sql . '.gz', 0640);
                $files[] = $sql . '.gz';
            }
        }

        $site = self::siteDir((string) $school['slug']);
        if (is_dir($site)) {
            $tgz = $base . '-files.tgz';
            exec('tar -czf ' . escapeshellarg($tgz) . ' -C ' . escapeshellarg(dirname($site)) . ' ' . escapeshellarg(basename($site)) . ' 2>&1', $out, $code);
            if ($code !== 0 || !is_file($tgz) || filesize($tgz) === 0) {
                @unlink($tgz);
                throw new \RuntimeException('site folder backup failed: ' . mb_substr(implode(' ', $out), 0, 300));
            }
            @chmod($tgz, 0640);
            $files[] = $tgz;
        }
        return $files;
    }

    /** Modules locked for a school, read from its own .env. */
    public function lockedModules(string $slug): array
    {
        $env = self::siteDir($slug) . '/.env';
        $raw = is_file($env) ? (string) ($this->readEnv($env)['SCHOOL_LOCKED_MODULES'] ?? '') : '';
        return array_values(array_intersect(array_map('trim', explode(',', $raw)), array_keys(Features::MODULES)));
    }

    /**
     * Writes a school's locked modules into its .env. Only that one line changes; every other
     * setting is kept byte for byte. Takes effect on the school's next request.
     */
    public function setLockedModules(int $id, array $locked): void
    {
        $locked = array_values(array_intersect(array_keys(Features::MODULES), $locked));
        $this->setEnvValues($id, ['SCHOOL_LOCKED_MODULES' => $locked === [] ? null : implode(',', $locked)]);
    }

    /** Single menu pages locked for a school (their addresses), read from its own .env. */
    public function lockedPages(string $slug): array
    {
        $env = self::siteDir($slug) . '/.env';
        $raw = is_file($env) ? (string) ($this->readEnv($env)['SCHOOL_LOCKED_PAGES'] ?? '') : '';
        return array_values(array_intersect(array_map('trim', explode(',', $raw)), array_column(Features::pages(), 'route')));
    }

    public function setLockedPages(int $id, array $routes): void
    {
        $routes = array_values(array_intersect(array_column(Features::pages(), 'route'), $routes));
        $this->setEnvValues($id, ['SCHOOL_LOCKED_PAGES' => $routes === [] ? null : implode(',', $routes)]);
    }

    /** A school's caps, e.g. ['students' => 200, 'staff' => null] (null = unlimited). */
    public function limits(string $slug): array
    {
        $env = self::siteDir($slug) . '/.env';
        $values = is_file($env) ? $this->readEnv($env) : [];
        $out = [];
        foreach (Features::LIMITS as $key => [$name]) {
            $raw = trim((string) ($values[$name] ?? ''));
            $out[$key] = ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : null;
        }
        return $out;
    }

    /** @param array<string,int|null> $limits null or 0 = unlimited */
    public function setLimits(int $id, array $limits): void
    {
        $values = [];
        foreach (Features::LIMITS as $key => [$name]) {
            $n = (int) ($limits[$key] ?? 0);
            if ($n < 0 || $n > 1000000) throw new \DomainException('Limits must be between 0 (unlimited) and 1,000,000.');
            $values[$name] = $n > 0 ? (string) $n : null;
        }
        $this->setEnvValues($id, $values);
    }

    /**
     * Sets (or, with null, removes) lines in a school's .env. Every other line is kept byte for
     * byte, and the file is replaced atomically. Takes effect on the school's next request.
     */
    private function setEnvValues(int $id, array $values): void
    {
        $school = $this->registry->find($id);
        if (!$school || !in_array($school['status'], ['active', 'suspended'], true)) throw new \DomainException('Only live or revoked schools have features to set.');
        $env = self::siteDir((string) $school['slug']) . '/.env';
        if (!is_file($env)) throw new \DomainException('This school has no settings file.');
        $content = (string) file_get_contents($env);
        foreach ($values as $name => $value) {
            if (!preg_match('/^SCHOOL_[A-Z_]+$/', $name)) throw new \DomainException('Invalid setting.');
            $content = (string) preg_replace('/^' . $name . '=.*(\R|$)/m', '', $content);
            if ($value !== null) $content = rtrim($content, "\r\n") . "\n" . $name . '="' . $value . "\"\n";
        }
        $this->write($env, $content, 0640);
    }

    /** Removes a school's site folder. Links into the shared code are unlinked, never followed. */
    private function removeSite(string $slug): void
    {
        $root = realpath(PlatformConfig::schoolsPath());
        $site = self::siteDir($slug);
        if (!is_dir($site) && !is_link($site)) return;
        $real = realpath($site);
        if ($root === false || $real === false || dirname($real) !== $root) throw new \RuntimeException('Refusing to delete a folder outside the schools folder.');
        $this->removeTree($real);
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || $this->isJunction($path)) {
            // A link to shared code: remove the link itself only.
            if (!(@unlink($path) || @rmdir($path))) throw new \RuntimeException("Could not remove link {$path}.");
            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
            }
            if (!@rmdir($path)) throw new \RuntimeException("Could not remove folder {$path}.");
            return;
        }
        // A plain file, or a dangling directory link on Windows (rmdir removes only the link, never contents).
        if ((file_exists($path) || is_link($path)) && !(@unlink($path) || @rmdir($path))) throw new \RuntimeException("Could not remove {$path}.");
    }

    /** Windows junctions (local development) report as folders; their target differs from the path. */
    private function isJunction(string $path): bool
    {
        if (PHP_OS_FAMILY !== 'Windows' || !is_dir($path)) return false;
        $real = realpath($path);
        $here = realpath(dirname($path));
        if ($real === false || $here === false) return false;
        return strcasecmp($real, $here . DIRECTORY_SEPARATOR . basename($path)) !== 0;
    }

    private function createDatabase(string $dbName, string $dbUser, string $password): void
    {
        $pdo = SchoolRegistry::serverConnection();
        // On the server a root-owned procedure does this, because MariaDB will not let the
        // provisioning account pass on grants from its `school\_%` wildcard. See docs/multi-school.md.
        $platformDb = PlatformConfig::get('DB_DATABASE', 'eskooly_platform');
        try {
            $pdo->prepare("CALL `{$platformDb}`.provision_school(:db,:user,:password)")->execute(['db' => $dbName, 'user' => $dbUser, 'password' => $password]);
            return;
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1305) throw $e; // 1305: procedure does not exist
        }
        $host = PlatformConfig::get('SCHOOL_DB_USER_HOST', 'localhost');
        $quotedHost = $pdo->quote($host);
        $quotedUser = $pdo->quote($dbUser);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("CREATE USER IF NOT EXISTS {$quotedUser}@{$quotedHost} IDENTIFIED BY " . $pdo->quote($password));
        // A retry generates a new password, so make the account match the .env written next.
        $pdo->exec("ALTER USER {$quotedUser}@{$quotedHost} IDENTIFIED BY " . $pdo->quote($password));
        $pdo->exec("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO {$quotedUser}@{$quotedHost}");
    }

    private function buildSite(string $slug, string $name, string $dbName, string $dbUser, string $dbPassword): string
    {
        $site = self::siteDir($slug);
        $code = PlatformConfig::codePath();
        if (!is_dir($code . '/app') || !is_file($code . '/vendor/autoload.php')) throw new \RuntimeException('PLATFORM_CODE_PATH does not point at the application.');
        foreach (['', '/public', '/public/uploads', '/storage', '/storage/logs', '/storage/sessions', '/storage/uploads', '/storage/tmp'] as $dir) {
            if (!is_dir($site . $dir) && !mkdir($site . $dir, 0775, true) && !is_dir($site . $dir)) throw new \RuntimeException("Could not create {$site}{$dir}.");
        }
        foreach (self::CODE_LINKS as $entry) $this->link($code . '/' . $entry, $site . '/' . $entry);
        foreach (self::PUBLIC_LINKS as $entry) $this->link($code . '/public/' . $entry, $site . '/public/' . $entry);

        $url = PlatformConfig::schoolUrl($slug);
        $secure = str_starts_with($url, 'https://') ? 'true' : 'false';
        $env = [
            'SCHOOL_SLUG' => $slug,
            'APP_NAME' => $name,
            'APP_ENV' => PlatformConfig::get('SCHOOL_APP_ENV', 'production'),
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'APP_KEY' => bin2hex(random_bytes(32)),
            'SESSION_NAME' => 'school_' . str_replace('-', '_', $slug) . '_session',
            'SESSION_SECURE' => $secure,
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => PlatformConfig::get('SCHOOL_DB_HOST', PlatformConfig::get('DB_HOST', '127.0.0.1')),
            'DB_PORT' => PlatformConfig::get('DB_PORT', '3306'),
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPassword,
            'PWA_ENABLED' => 'true',
        ];
        $existing = is_file($site . '/.env') ? $this->readEnv($site . '/.env') : [];
        // Keep a school's own later additions (Google, SMS keys) and its APP_KEY, which encrypts stored secrets.
        $env = array_replace($existing, $env, isset($existing['APP_KEY']) ? ['APP_KEY' => $existing['APP_KEY']] : []);
        $lines = ["# Generated by the school sign-up platform for {$slug}. Keep this file private."];
        foreach ($env as $key => $value) $lines[] = $key . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value) . '"';
        $this->write($site . '/.env', implode("\n", $lines) . "\n", 0640);

        // A revoked school shows the main portal's status page ("access revoked"), not a bare error.
        $front = sprintf(<<<'PHP'
<?php

declare(strict_types=1);

// Generated for one school: BASE_PATH is this site folder, the code is linked in.
define('BASE_PATH', dirname(__DIR__));
if (is_file(BASE_PATH . '/SUSPENDED')) {
    header('Cache-Control: no-store');
    header('Location: ' . %s, true, 302);
    exit;
}
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';
$app->run(App\Core\Request::capture())->send();

PHP, var_export(url('/school-status?school=' . rawurlencode($slug)), true));
        $this->write($site . '/public/index.php', $front, 0644);
        $this->write($site . '/public/router.php', "<?php\n// Local php -S router for this school folder.\n\$path = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';\nif (\$path !== '/' && is_file(__DIR__ . \$path)) return false;\nrequire __DIR__ . '/index.php';\n", 0644);
        $this->write($site . '/public/manifest.webmanifest', $this->manifest($name, $code), 0644);
        return $site;
    }

    /** The main portal's manifest with this school's name; icons fall back to the neutral logo. */
    private function manifest(string $name, string $code): string
    {
        $manifest = json_decode((string) @file_get_contents($code . '/public/manifest.webmanifest'), true) ?: ['start_url' => '/', 'display' => 'standalone'];
        $manifest['name'] = $name;
        $manifest['short_name'] = mb_substr(explode(' ', $name)[0], 0, 12);
        $manifest['icons'] = [['src' => '/assets/images/school-logo.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']];
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function migrate(string $site): void
    {
        $php = PlatformConfig::get('PHP_BINARY', PHP_BINARY);
        $command = [$php, PlatformConfig::codePath() . '/scripts/school.php', $site, 'database/migrate.php'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, self::cleanEnvironment());
        if (!is_resource($process)) throw new \RuntimeException('Could not start the migration process.');
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new \RuntimeException('Migrations failed: ' . mb_substr(trim($output), -1500));
    }

    /** Names the school after the sign-up and creates its administrator with the chosen password. */
    private function seedSchool(string $dbName, array $school): void
    {
        $pdo = SchoolRegistry::serverConnection($dbName);
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $instituteId = (int) $pdo->query('SELECT MIN(id) FROM institutes')->fetchColumn();
            if ($instituteId === 0) {
                $pdo->prepare("INSERT INTO institutes(name,target_line,phone,website,address,country,created_at,updated_at) VALUES(:n,'','','','','Pakistan',:c,:u)")->execute(['n' => $school['name'], 'c' => $now, 'u' => $now]);
                $instituteId = (int) $pdo->lastInsertId();
            }
            // The first migration seeds placeholder contact details; replace them with the school's own.
            $pdo->prepare("UPDATE institutes SET name=:n,target_line='',phone=:p,website=:w,address=:a,updated_at=:u WHERE id=:id")
                ->execute(['n' => $school['name'], 'p' => $school['admin_phone'], 'w' => PlatformConfig::schoolUrl((string) $school['slug']), 'a' => $school['city'], 'u' => $now, 'id' => $instituteId]);
            $pdo->prepare("INSERT IGNORE INTO institute_preferences(institute_id,timezone,currency,currency_symbol,created_at,updated_at) VALUES(:i,'Asia/Karachi','PKR','Rs',:c,:u)")->execute(['i' => $instituteId, 'c' => $now, 'u' => $now]);
            if (!(int) $pdo->query('SELECT COUNT(*) FROM account_heads')->fetchColumn()) {
                $pdo->prepare("INSERT INTO account_heads(institute_id,name,type,status,created_at,updated_at) VALUES(:i,'General','both','active',:c,:u)")->execute(['i' => $instituteId, 'c' => $now, 'u' => $now]);
            }
            $roleId = $pdo->query("SELECT id FROM roles WHERE slug='admin'")->fetchColumn();
            if (!$roleId) throw new \RuntimeException('The administrator role is missing after migrations.');
            $find = $pdo->prepare('SELECT id FROM users WHERE email=:e');
            $find->execute(['e' => strtolower((string) $school['admin_email'])]);
            $userId = (int) $find->fetchColumn();
            if ($userId === 0) {
                $pdo->prepare("INSERT INTO users(name,email,password_hash,status,created_at,updated_at) VALUES(:n,:e,:p,'active',:c,:u)")
                    ->execute(['n' => $school['admin_name'], 'e' => strtolower((string) $school['admin_email']), 'p' => $school['admin_password_hash'], 'c' => $now, 'u' => $now]);
                $userId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(:u,:r)')->execute(['u' => $userId, 'r' => $roleId]);
            $pdo->prepare('INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) VALUES(:u,:i,:c)')->execute(['u' => $userId, 'i' => $instituteId, 'c' => $now]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Only what a PHP process needs to start; the school's settings come from its own .env. */
    public static function cleanEnvironment(): array
    {
        $keep = [];
        foreach (['PATH', 'Path', 'SystemRoot', 'SYSTEMROOT', 'TEMP', 'TMP', 'TMPDIR', 'HOME', 'LANG', 'COMSPEC', 'PATHEXT', 'WINDIR'] as $key) {
            $value = getenv($key);
            if ($value !== false) $keep[$key] = $value;
        }
        return $keep;
    }

    private function link(string $target, string $path): void
    {
        if (is_link($path) || file_exists($path)) return;
        if (!file_exists($target)) throw new \RuntimeException("Missing shared file {$target}.");
        if (@symlink($target, $path)) return;
        // Windows without symlink rights (local development only): junction for folders, copy for files.
        if (PHP_OS_FAMILY === 'Windows') {
            if (is_dir($target)) {
                exec('cmd /c mklink /J ' . escapeshellarg(str_replace('/', '\\', $path)) . ' ' . escapeshellarg(str_replace('/', '\\', $target)), $out, $code);
                if ($code === 0) return;
            } elseif (copy($target, $path)) {
                return;
            }
        }
        throw new \RuntimeException("Could not link {$path}.");
    }

    private function write(string $path, string $content, int $mode): void
    {
        if (file_put_contents($path . '.tmp', $content) === false || !rename($path . '.tmp', $path)) throw new \RuntimeException("Could not write {$path}.");
        @chmod($path, $mode);
    }

    private function readEnv(string $file): array
    {
        $values = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $values[$key] = stripcslashes(trim($value, '"'));
        }
        return $values;
    }
}
