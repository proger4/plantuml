<?php
declare(strict_types=1);

/**
 * Minimal HTTP entrypoint (NOT Symfony), but aligned with docs/api.puml contracts.
 * TODO: replace this file with Symfony HTTP kernel + controllers.
 */

if (is_file(__DIR__ . '/vendor/autoload.php')) {
  require __DIR__ . '/vendor/autoload.php';
} else {
  spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
      return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
      require $file;
    }
  });
}

$container = require __DIR__ . '/src/bootstrap.php';
$useCases = $container['useCases'];
$pdo = $container['db']->pdo();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($method === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function jsonBody(): array {
  $raw = file_get_contents('php://input');
  $d = json_decode($raw ?: '[]', true);
  return is_array($d) ? $d : [];
}

function issueToken(int $userId, string $name, string $color): string {
  return base64_encode($userId . ':' . $name . ':' . $color);
}

function authUserId(PDO $pdo): int {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (preg_match('/Bearer\s+(.+)/', $h, $m)) {
    $decoded = base64_decode(trim($m[1]), true);
    if (!is_string($decoded)) return 0;
    [$id] = array_pad(explode(':', $decoded, 2), 2, '');
    $userId = (int)$id;
    if ($userId <= 0) return 0;

    $st = $pdo->prepare("SELECT id FROM users WHERE id = :id");
    $st->execute([':id' => $userId]);
    $row = $st->fetch();
    return $row ? $userId : 0;
  }
  return 0;
}

function respond(int $status, array $data): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->query("PRAGMA table_info($table)");
  if (!$st) return false;
  foreach ($st->fetchAll() ?: [] as $row) {
    if ((string)($row['name'] ?? '') === $column) {
      return true;
    }
  }
  return false;
}

try {
  // Health
  if ($method === 'GET' && $path === '/health') {
    respond(200, ['ok' => true]);
  }

  // Auth (stub)
  if ($method === 'POST' && $path === '/api/auth/login') {
    $b = jsonBody();
    $name = trim((string)($b['name'] ?? ''));
    $password = (string)($b['password'] ?? '');
    if ($name === '' || $password === '') {
      respond(400, ['ok' => false, 'error' => 'name/password required']);
    }

    $st = $pdo->prepare("SELECT id, name, password_hash, color FROM users WHERE name = :name");
    $st->execute([':name' => $name]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
      respond(401, ['ok' => false, 'error' => 'invalid_credentials']);
    }

    respond(200, [
      'token' => issueToken((int)$user['id'], (string)$user['name'], (string)($user['color'] ?? '#f59e0b')),
      'user' => [
        'id' => (int)$user['id'],
        'name' => (string)$user['name'],
        'color' => (string)($user['color'] ?? '#f59e0b'),
      ],
    ]);
  }

  if ($method === 'POST' && $path === '/api/auth/logout') {
    respond(200, ['ok' => true]);
  }

  if ($method === 'GET' && $path === '/api/me/settings') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);

    $hasSidebar = hasColumn($pdo, 'user_settings', 'sidebar_width');
    $hasTrace = hasColumn($pdo, 'user_settings', 'trace_height');
    $selectCols = "editor_font_size, preview_split";
    if ($hasSidebar) $selectCols .= ", sidebar_width";
    if ($hasTrace) $selectCols .= ", trace_height";
    $st = $pdo->prepare("SELECT $selectCols FROM user_settings WHERE user_id = :uid");
    $st->execute([':uid' => $userId]);
    $row = $st->fetch() ?: [];
    if (!array_key_exists('sidebar_width', $row)) $row['sidebar_width'] = 240;
    if (!array_key_exists('trace_height', $row)) $row['trace_height'] = 112;
    if (!array_key_exists('editor_font_size', $row)) $row['editor_font_size'] = 13;
    if (!array_key_exists('preview_split', $row)) $row['preview_split'] = 0.5;
    respond(200, [
      'ok' => true,
      'settings' => $row,
    ]);
  }

  if ($method === 'PUT' && $path === '/api/me/settings') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $b = jsonBody();

    $fontSize = max(11, min(22, (int)($b['editor_font_size'] ?? 13)));
    $split = (float)($b['preview_split'] ?? 0.5);
    $split = max(0.2, min(0.8, $split));
    $sidebarWidth = max(180, min(420, (int)($b['sidebar_width'] ?? 240)));
    $traceHeight = max(80, min(280, (int)($b['trace_height'] ?? 112)));

    $hasSidebar = hasColumn($pdo, 'user_settings', 'sidebar_width');
    $hasTrace = hasColumn($pdo, 'user_settings', 'trace_height');

    if ($hasSidebar && $hasTrace) {
      $st = $pdo->prepare("
        INSERT INTO user_settings(user_id, editor_font_size, preview_split, sidebar_width, trace_height, updated_at)
        VALUES(:uid, :fs, :split, :sidebar, :trace, datetime('now'))
        ON CONFLICT(user_id) DO UPDATE SET
          editor_font_size = excluded.editor_font_size,
          preview_split = excluded.preview_split,
          sidebar_width = excluded.sidebar_width,
          trace_height = excluded.trace_height,
          updated_at = excluded.updated_at
      ");
      $st->execute([':uid' => $userId, ':fs' => $fontSize, ':split' => $split, ':sidebar' => $sidebarWidth, ':trace' => $traceHeight]);
    } else {
      $st = $pdo->prepare("
        INSERT INTO user_settings(user_id, editor_font_size, preview_split, updated_at)
        VALUES(:uid, :fs, :split, datetime('now'))
        ON CONFLICT(user_id) DO UPDATE SET
          editor_font_size = excluded.editor_font_size,
          preview_split = excluded.preview_split,
          updated_at = excluded.updated_at
      ");
      $st->execute([':uid' => $userId, ':fs' => $fontSize, ':split' => $split]);
    }

    respond(200, ['ok' => true, 'settings' => [
      'editor_font_size' => $fontSize,
      'preview_split' => $split,
      'sidebar_width' => $sidebarWidth,
      'trace_height' => $traceHeight
    ]]);
  }

  if ($method === 'GET' && $path === '/api/me/documents') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $filter = (string)($_GET['filter'] ?? 'personal');
    $docs = $useCases->listDocuments($userId, $filter);
    respond(200, ['ok' => true, 'documents' => $docs]);
  }

  if ($method === 'GET' && $path === '/api/me/stats') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);

    $revCount = (int)$pdo->query("
      SELECT COUNT(*)
      FROM revisions r
      INNER JOIN documents d ON d.id = r.document_id
      WHERE d.author_id = $userId
    ")->fetchColumn();
    $attemptCount = (int)$pdo->query("
      SELECT COUNT(*)
      FROM attempts
      WHERE user_id = $userId
    ")->fetchColumn();

    respond(200, ['ok' => true, 'stats' => ['revisions' => $revCount, 'attempts' => $attemptCount]]);
  }

  if ($method === 'POST' && $path === '/api/documents') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $b = jsonBody();
    $code = (string)($b['code'] ?? "@startuml\n@enduml");
    $isPublic = (bool)($b['isPublic'] ?? false);
    $doc = $useCases->createDocument($userId, $code, $isPublic);
    respond(201, ['ok' => true, 'document' => $doc]);
  }

  if ($method === 'GET' && preg_match('#^/api/documents/(\d+)$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    $payload = $useCases->getDocumentWithPreview($userId, $docId);
    respond(200, ['ok' => true, ...$payload]);
  }

  if ($method === 'PUT' && preg_match('#^/api/documents/(\d+)$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $b = jsonBody();
    $isPublic = (bool)($b['isPublic'] ?? false);
    $slug = array_key_exists('slug', $b) ? (string)$b['slug'] : null;
    $doc = $useCases->publishDocument($userId, $docId, $isPublic, $slug);
    // TODO: when full metadata DTO appears, support all mutable fields from docs.
    respond(200, ['ok' => true, 'document' => $doc]);
  }

  if ($method === 'DELETE' && preg_match('#^/api/documents/(\d+)$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $useCases->deleteDocument($userId, $docId);
    respond(200, ['ok' => true]);
  }

  if ($method === 'POST' && preg_match('#^/api/documents/(\d+)/favorite$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $useCases->setFavorite($userId, $docId, true);
    respond(200, ['ok' => true]);
  }

  if ($method === 'DELETE' && preg_match('#^/api/documents/(\d+)/favorite$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $useCases->setFavorite($userId, $docId, false);
    respond(200, ['ok' => true]);
  }

  if ($method === 'POST' && preg_match('#^/api/documents/(\d+)/publish$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);
    $b = jsonBody();
    $isPublic = array_key_exists('isPublic', $b) ? (bool)$b['isPublic'] : true;
    $slug = array_key_exists('slug', $b) ? (string)$b['slug'] : null;
    $doc = $useCases->publishDocument($userId, $docId, $isPublic, $slug);
    respond(200, ['ok' => true, 'document' => $doc]);
  }

  if ($method === 'POST' && preg_match('#^/api/documents/(\d+)/revisions$#', $path, $m)) {
    $docId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);

    $b = jsonBody();
    $code = (string)($b['code'] ?? '');
    $result = $useCases->saveRevisionAndRender($userId, $docId, $code);
    respond(200, $result);
  }

  if ($method === 'POST' && $path === '/api/sessions') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);

    $b = jsonBody();
    $docId = (int)($b['documentId'] ?? 0);
    if ($docId <= 0) respond(400, ['ok' => false, 'error' => 'documentId required']);

    $s = $useCases->joinSession($userId, $docId);
    $wsEnabled = interface_exists(\Ratchet\MessageComponentInterface::class);
    respond(200, [
      'ok' => true,
      'sessionId' => $s['sessionId'],
      'wsEnabled' => $wsEnabled,
      'wsUrl' => $wsEnabled ? $s['wsUrl'] : null,
      'docId' => $docId,
    ]);
  }

  if ($method === 'POST' && $path === '/api/quizzes/random') {
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);

    $quiz = $pdo->query("
      SELECT q.id, q.formulation, d.id AS before_id, d.code AS before_code
      FROM quizzes q
      JOIN documents d ON d.id = q.before_document_id
      ORDER BY RANDOM()
      LIMIT 1
    ")->fetch();
    if (!$quiz) {
      respond(404, ['ok' => false, 'error' => 'no_quizzes']);
    }
    respond(200, [
      'ok' => true,
      'quiz' => [
        'id' => (int)$quiz['id'],
        'formulation' => (string)$quiz['formulation'],
      ],
      'beforeDoc' => [
        'id' => (int)$quiz['before_id'],
        'code' => (string)$quiz['before_code'],
      ],
    ]);
  }

  if ($method === 'POST' && preg_match('#^/api/quizzes/(\d+)/submit$#', $path, $m)) {
    $quizId = (int)$m[1];
    $userId = authUserId($pdo);
    if ($userId <= 0) respond(401, ['ok' => false, 'error' => 'auth_required']);

    $b = jsonBody();
    $code = (string)($b['code'] ?? '');
    if ($code === '') {
      respond(400, ['ok' => false, 'error' => 'code required']);
    }

    $quizSt = $pdo->prepare("SELECT * FROM quizzes WHERE id = :id");
    $quizSt->execute([':id' => $quizId]);
    $quiz = $quizSt->fetch();
    if (!$quiz) respond(404, ['ok' => false, 'error' => 'quiz_not_found']);

    $requiredDocSt = $pdo->prepare("SELECT code FROM documents WHERE id = :id");
    $requiredDocSt->execute([':id' => (int)$quiz['required_document_id']]);
    $required = (string)($requiredDocSt->fetchColumn() ?: '');

    // TODO: replace naive score with AST/semantic comparison of diagrams.
    $score = trim($code) === trim($required) ? 100 : 0;

    $tryoutRevision = $useCases->saveRevisionAndRender($userId, 1, $code);
    $tryoutRevisionId = (int)$tryoutRevision['revisionId'];

    $attemptSt = $pdo->prepare("
      INSERT INTO attempts(user_id, quiz_id, tryout_revision_id, ts_created, score)
      VALUES(:uid, :qid, :rid, datetime('now'), :score)
    ");
    $attemptSt->execute([
      ':uid' => $userId,
      ':qid' => $quizId,
      ':rid' => $tryoutRevisionId,
      ':score' => $score,
    ]);

    respond(200, ['ok' => true, 'score' => $score, 'isPass' => $score >= 100, 'attemptId' => (int)$pdo->lastInsertId()]);
  }

  respond(404, ['ok' => false, 'error' => 'not_found', 'path' => $path]);
} catch (Throwable $e) {
  if ($e->getMessage() === 'locked_by_other') {
    respond(409, ['ok' => false, 'error' => 'locked_by_other']);
  }
  if ($e->getMessage() === 'invalid_transition') {
    respond(409, ['ok' => false, 'error' => 'invalid_transition']);
  }
  if ($e->getMessage() === 'auth_required') {
    respond(401, ['ok' => false, 'error' => 'auth_required']);
  }
  if ($e->getMessage() === 'Forbidden' || $e->getMessage() === 'forbidden') {
    respond(403, ['ok' => false, 'error' => 'forbidden']);
  }
  respond(500, ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()]);
}
