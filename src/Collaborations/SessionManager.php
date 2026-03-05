<?php
declare(strict_types=1);

namespace App\Collaborations;

use Ratchet\ConnectionInterface;

/**
 * In-memory collaboration session registry.
 * TODO (later):
 * - presence list (userIds)
 * - lease-based locks
 * - scaling: Redis pub/sub or WS fanout layer
 */
final class SessionManager
{
  /** @var ConnectionInterface docId => [resourceId => conn] */
  private array $conns = [];

  public function join(int $docId, ConnectionInterface $conn): void
  {
    $this->conns[$docId] ??= [];
    $this->conns[$docId][$conn->resourceId] = $conn;
  }

  public function leave(int $docId, ConnectionInterface $conn): void
  {
    unset($this->conns[$docId][$conn->resourceId]);
    if (empty($this->conns[$docId])) {
      unset($this->conns[$docId]);
    }
  }

  /**
   * @return list<ConnectionInterface>
   */
  public function connections(int $docId): array
  {
    return array_values($this->conns[$docId] ?? []);
  }
}
