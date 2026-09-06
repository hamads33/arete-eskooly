<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }
        $dsn = $this->config['driver'] === 'sqlite'
            ? 'sqlite:' . $this->sqlitePath((string) $this->config['database'])
            : sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $this->config['driver'], $this->config['host'], $this->config['port'], $this->config['database'], $this->config['charset']);
        return $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function sqlitePath(string $database): string
    {
        if (str_starts_with($database, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $database)) {
            return $database;
        }
        return BASE_PATH . '/' . ltrim(str_replace('\\', '/', $database), '/');
    }

    public function query(string $sql, array $bindings = []): \PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }
}
