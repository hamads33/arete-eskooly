<?php

declare(strict_types=1);

namespace App\Platform;

use PDO;

/**
 * Sign-up requests and the schools they became, kept in the platform's own database
 * (PLATFORM_DB_DATABASE) — never in the main school's database.
 */
final class SchoolRegistry
{
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** Connects with the provisioning account; it may create databases and users. */
    public static function serverConnection(?string $database = null): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', PlatformConfig::get('DB_HOST', '127.0.0.1'), (int) PlatformConfig::get('DB_PORT', '3306'));
        if ($database !== null) $dsn .= ';dbname=' . $database;
        return new PDO($dsn, PlatformConfig::get('DB_USERNAME'), PlatformConfig::get('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) return $this->pdo;
        $name = PlatformConfig::get('DB_DATABASE', 'eskooly_platform');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) throw new \RuntimeException('PLATFORM_DB_DATABASE has an invalid name.');
        $server = self::serverConnection();
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->pdo = self::serverConnection($name);
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schools (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(40) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            city VARCHAR(120) NOT NULL DEFAULT '',
            admin_name VARCHAR(120) NOT NULL,
            admin_email VARCHAR(190) NOT NULL,
            admin_phone VARCHAR(40) NOT NULL DEFAULT '',
            admin_password_hash VARCHAR(255) NOT NULL,
            status ENUM('pending','active','rejected','suspended','failed') NOT NULL DEFAULT 'pending',
            db_name VARCHAR(64) NULL,
            db_user VARCHAR(32) NULL,
            note VARCHAR(500) NOT NULL DEFAULT '',
            last_error TEXT NULL,
            signup_ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            approved_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_school_status (status),
            INDEX idx_school_email (admin_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $this->pdo;
    }

    private function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public function all(?string $status = null): array
    {
        return $status === null
            ? $this->run("SELECT * FROM schools ORDER BY FIELD(status,'pending','failed','active','suspended','rejected'), created_at DESC")->fetchAll()
            : $this->run('SELECT * FROM schools WHERE status=:s ORDER BY created_at DESC', ['s' => $status])->fetchAll();
    }

    public function find(int $id): ?array
    {
        return $this->run('SELECT * FROM schools WHERE id=:id', ['id' => $id])->fetch() ?: null;
    }

    /** The current (non-rejected first, then newest) request for a subdomain. */
    public function findBySlug(string $slug): ?array
    {
        return $this->run("SELECT * FROM schools WHERE slug=:s ORDER BY status='rejected', created_at DESC LIMIT 1", ['s' => $slug])->fetch() ?: null;
    }

    /** An application from this email that is still waiting or already live. */
    public function openByEmail(string $email): ?array
    {
        return $this->run("SELECT * FROM schools WHERE admin_email=:e AND status IN('pending','failed','active','suspended') ORDER BY created_at DESC LIMIT 1", ['e' => $email])->fetch() ?: null;
    }

    public function slugTaken(string $slug): bool
    {
        return (bool) $this->run("SELECT 1 FROM schools WHERE slug=:s AND status<>'rejected'", ['s' => $slug])->fetchColumn();
    }

    public function pendingFromIp(string $ip): int
    {
        return (int) $this->run("SELECT COUNT(*) FROM schools WHERE signup_ip=:ip AND status='pending' AND created_at>:since", ['ip' => $ip, 'since' => date('Y-m-d H:i:s', time() - 86400)])->fetchColumn();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        // A rejected request frees its subdomain; drop it so the new request can take the slug.
        $this->run("DELETE FROM schools WHERE slug=:s AND status='rejected'", ['s' => $data['slug']]);
        $this->run('INSERT INTO schools(slug,name,city,admin_name,admin_email,admin_phone,admin_password_hash,signup_ip,created_at,updated_at)
            VALUES(:slug,:name,:city,:admin_name,:admin_email,:admin_phone,:hash,:ip,:c,:u)', $data + ['c' => $now, 'u' => $now]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->run('DELETE FROM schools WHERE id=:id', ['id' => $id]);
    }

    public function update(int $id, array $fields): void
    {
        $sets = implode(',', array_map(static fn(string $k): string => "{$k}=:{$k}", array_keys($fields)));
        $this->run("UPDATE schools SET {$sets},updated_at=:updated_at WHERE id=:id", $fields + ['updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);
    }
}
