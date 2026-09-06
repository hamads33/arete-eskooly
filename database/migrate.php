<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
/** @var \App\Core\App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$pdo = $app->database()->connection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$idDefinition = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id {$idDefinition}, migration VARCHAR(255) NOT NULL UNIQUE, executed_at DATETIME NOT NULL)");
$applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

foreach (glob(BASE_PATH . '/database/migrations/*.php') ?: [] as $file) {
    $name = basename($file, '.php');
    if (in_array($name, $applied, true)) continue;
    $migration = require $file;
    // MySQL implicitly commits DDL, so wrapping schema migrations in a PDO
    // transaction leaves no active transaction to commit. SQLite DDL remains
    // transactional and can safely use the existing rollback behavior.
    $transactional = $driver === 'sqlite';
    if ($transactional) $pdo->beginTransaction();
    try {
        $migration($pdo);
        $statement = $pdo->prepare('INSERT INTO migrations (migration, executed_at) VALUES (:migration, :executed_at)');
        $statement->execute(['migration' => $name, 'executed_at' => date('Y-m-d H:i:s')]);
        if ($transactional) $pdo->commit();
        echo "Migrated: {$name}" . PHP_EOL;
    } catch (Throwable $exception) {
        if ($transactional && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
