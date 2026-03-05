<?php
declare(strict_types=1);

namespace App\Infrastructure\Ws;

use App\Application\UseCases;
use App\Collaborations\SessionManager;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

/**
 * Ratchet WS collaboration server.
 * Ref: https://github.com/ratchetphp/Ratchet
 * WebSocket RFC: https://datatracker.ietf.org/doc/html/rfc6455
 *
 * Protocol:
 * - Client -> AUTH {token}
 * - Client -> JOIN {documentId}
 * - Client -> DOC_COLLABORATOR_ACTION {action:'acquire_lock'|'release_lock'}
 * - Client -> DOC_EDIT {docId, change:{type, range:{left,right}, text}, caret:{left,right}}
 * - Client -> DOC_RENDER_REQUEST {docId}
 *
 * TODO (later):
 * - Replace token stub with Symfony Security + JWT/session cookies.
 * - Add correlationId, optimistic concurrency, debounced render, presence list.
 */
final class CollabServer implements MessageComponentInterface
{
  /** @var array<int, array{userId:int, docId:int|null}> */
  private array $ctx = [];

  public function __construct(
    private UseCases $useCases,
    private SessionManager $sessions
  ) {}

  public function onOpen(ConnectionInterface $conn): void
  {
    $this->ctx[$conn->resourceId] = ['userId' => 0, 'docId' => null];
    $this->send($conn, 'HELLO', ['server' => 'plantuml-studio-ws']);
  }

  public function onMessage(ConnectionInterface $from, $msg): void
  {
    $data = json_decode((string)$msg, true);
    if (!is_array($data)) {
      $this->sendError($from, 'bad_json', 'Message must be JSON');
      return;
    }

    $event = (string)($data['event'] ?? '');
    $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

    try {
      match ($event) {
        'AUTH' => $this->handleAuth($from, $payload),
        'JOIN' => $this->handleJoin($from, $payload),
        'LEAVE' => $this->handleLeave($from),
        'DOC_COLLABORATOR_ACTION' => $this->handleAction($from, $payload),
        'DOC_EDIT' => $this->handleEdit($from, $payload),
        'DOC_RENDER_REQUEST' => $this->handleRender($from, $payload),
        default => $this->sendError($from, 'unknown_event', "Unknown event: $event"),
      };
    } catch (\Throwable $e) {
      $this->sendError($from, 'server_error', $e->getMessage());
    }
  }

  public function onClose(ConnectionInterface $conn): void
  {
    $this->handleLeave($conn);
    unset($this->ctx[$conn->resourceId]);
  }

  public function onError(ConnectionInterface $conn, \Exception $e): void
  {
    $this->sendError($conn, 'ws_error', $e->getMessage());
    $conn->close();
  }

  private function handleAuth(ConnectionInterface $conn, array $p): void
  {
    $token = (string)($p['token'] ?? '');
    $decoded = base64_decode($token, true);
    $userId = 0;
    if (is_string($decoded)) {
      [$id] = array_pad(explode(':', $decoded, 2), 2, '');
      $userId = max(0, (int)$id);
    }

    $this->ctx[$conn->resourceId]['userId'] = $userId;
    $this->send($conn, 'AUTH_ACK', ['userId' => $userId]);
  }

  private function handleJoin(ConnectionInterface $conn, array $p): void
  {
    $docId = (int)($p['documentId'] ?? 0);
    if ($docId <= 0) {
      $this->sendError($conn, 'bad_request', 'documentId required');
      return;
    }

    $userId = $this->ctx[$conn->resourceId]['userId'];
    if ($userId <= 0) {
      $this->sendError($conn, 'auth_required', 'AUTH first');
      return;
    }

    $this->ctx[$conn->resourceId]['docId'] = $docId;

    $this->sessions->join($docId, $conn);
    $doc = $this->useCases->getDocument($userId, $docId);

    $this->send($conn, 'DOC_SNAPSHOT', [
      'docId' => $docId,
      'code' => $doc['code'],
      'revision' => (int)$doc['current_revision'],
      'lockUserId' => $this->useCases->getLockUserId($docId),
    ]);

    $this->broadcast($docId, 'DOC_COLLABORATOR_JOIN', [
      'docId' => $docId,
      'userId' => $userId,
    ], except: $conn);
  }

  private function handleLeave(ConnectionInterface $conn): void
  {
    $docId = $this->ctx[$conn->resourceId]['docId'];
    $userId = $this->ctx[$conn->resourceId]['userId'];
    if ($docId) {
      $this->sessions->leave((int)$docId, $conn);

      $this->broadcast((int)$docId, 'DOC_COLLABORATOR_LEAVE', [
        'docId' => (int)$docId,
        'userId' => (int)$userId,
      ], except: $conn);
    }
    $this->ctx[$conn->resourceId]['docId'] = null;
  }

  private function handleAction(ConnectionInterface $conn, array $p): void
  {
    $docId = (int)($this->ctx[$conn->resourceId]['docId'] ?? 0);
    $userId = (int)($this->ctx[$conn->resourceId]['userId'] ?? 0);
    $action = (string)($p['action'] ?? '');

    if ($docId <= 0 || $userId <= 0) {
      $this->sendError($conn, 'bad_state', 'JOIN and AUTH required');
      return;
    }

    if ($action === 'acquire_lock') {
      $this->useCases->acquireLock($userId, $docId);
      $this->send($conn, 'LOCK_ACQUIRED', ['docId' => $docId, 'userId' => $userId]);
      $this->broadcast($docId, 'LOCK_CHANGED', ['docId' => $docId, 'lockUserId' => $userId], except: $conn);
      return;
    }

    if ($action === 'release_lock') {
      $this->useCases->releaseLock($userId, $docId);
      $this->send($conn, 'LOCK_RELEASED', ['docId' => $docId, 'userId' => $userId]);
      $this->broadcast($docId, 'LOCK_CHANGED', ['docId' => $docId, 'lockUserId' => null], except: $conn);
      return;
    }

    $this->sendError($conn, 'bad_request', 'Unknown action');
  }

  private function handleEdit(ConnectionInterface $conn, array $p): void
  {
    $docId = (int)($this->ctx[$conn->resourceId]['docId'] ?? 0);
    $userId = (int)($this->ctx[$conn->resourceId]['userId'] ?? 0);
    if ($docId <= 0 || $userId <= 0) {
      $this->sendError($conn, 'bad_state', 'JOIN and AUTH required');
      return;
    }

    $result = $this->useCases->applyEdit($userId, $docId, $p);

    $this->send($conn, 'DOC_EDIT_ACK', $result);

    $this->broadcast($docId, 'DOC_EDIT_APPLIED', [
      'docId' => $docId,
      'userId' => $userId,
      'change' => $p['change'] ?? null,
      'revision' => $result['revision'] ?? null,
    ], except: $conn);
  }

  private function handleRender(ConnectionInterface $conn, array $p): void
  {
    $docId = (int)($this->ctx[$conn->resourceId]['docId'] ?? 0);
    $userId = (int)($this->ctx[$conn->resourceId]['userId'] ?? 0);
    if ($docId <= 0 || $userId <= 0) {
      $this->sendError($conn, 'bad_state', 'JOIN and AUTH required');
      return;
    }

    $result = $this->useCases->renderLatest($userId, $docId);
    $this->broadcast($docId, 'DOC_RENDER_FINISHED', $result);
  }

  private function broadcast(int $docId, string $event, array $payload, ?ConnectionInterface $except = null): void
  {
    foreach ($this->sessions->connections($docId) as $c) {
      if ($except && $c === $except) continue;
      $this->send($c, $event, $payload);
    }
  }

  private function send(ConnectionInterface $conn, string $event, array $payload): void
  {
    $conn->send(json_encode(['event' => $event, 'payload' => $payload], JSON_UNESCAPED_UNICODE));
  }

  private function sendError(ConnectionInterface $conn, string $code, string $message): void
  {
    $this->send($conn, 'ERROR', ['code' => $code, 'message' => $message]);
  }
}
