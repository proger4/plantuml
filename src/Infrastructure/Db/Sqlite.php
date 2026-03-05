<?php
declare(strict_types=1);

namespace App\Infrastructure\Db;

use PDO;

final class Sqlite
{
  private PDO $pdo;

  public function __construct(string $path)
  {
    $dir = dirname($path);
    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }

    $this->pdo = new PDO('sqlite:' . $path, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $this->pdo->exec("PRAGMA foreign_keys = ON;");
  }

  public function pdo(): PDO
  {
    return $this->pdo;
  }
}
