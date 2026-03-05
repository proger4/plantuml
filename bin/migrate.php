<?php
declare(strict_types=1);

/**
 * Minimal SQL migrations runner.
 * Usage:
 *   php bin/migrate.php
 */
$root = dirname(__DIR__);
$dbPath = $root . '/var/app.sqlite';

if (!is_dir($root . '/var')) {
  mkdir($root . '/var', 0777, true);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("PRAGMA foreign_keys = ON;");

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, ts_applied TEXT NOT NULL);");

$migrationsDir = $root . '/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
  $version = basename($file);
  $stmt = $pdo->prepare("SELECT version FROM schema_migrations WHERE version = :v");
  $stmt->execute([':v' => $version]);
  if ($stmt->fetch()) {
    echo "skip $version\n";
    continue;
  }

  echo "apply $version\n";
  $sql = file_get_contents($file);
  if ($sql === false) {
    throw new RuntimeException("Cannot read $file");
  }

  $pdo->beginTransaction();
  try {
    $pdo->exec($sql);
    $ins = $pdo->prepare("INSERT INTO schema_migrations(version, ts_applied) VALUES (:v, datetime('now'))");
    $ins->execute([':v' => $version]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

echo "done\n";
