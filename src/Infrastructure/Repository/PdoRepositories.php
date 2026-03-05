<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Application\Ports\DocumentRepository;
use App\Application\Ports\RevisionRepository;
use App\Application\Ports\SessionRepository;
use PDO;

final class PdoRepositories
{
  private PdoDocumentRepository $documents;
  private PdoRevisionRepository $revisions;
  private PdoSessionRepository $sessions;

  public function __construct(PDO $pdo)
  {
    $this->documents = new PdoDocumentRepository($pdo);
    $this->revisions = new PdoRevisionRepository($pdo);
    $this->sessions = new PdoSessionRepository($pdo);
  }

  public function documents(): DocumentRepository { return $this->documents; }
  public function revisions(): RevisionRepository { return $this->revisions; }
  public function sessions(): SessionRepository { return $this->sessions; }
}

final class PdoDocumentRepository implements DocumentRepository
{
  public function __construct(private PDO $pdo) {}

  public function getById(int $id): array
  {
    $st = $this->pdo->prepare("SELECT * FROM documents WHERE id = :id AND is_deleted = 0");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
      throw new \RuntimeException("Document not found: $id");
    }
    return $row;
  }

  public function listByFilter(int $userId, string $filter): array
  {
    $sql = match ($filter) {
      'favorites' => "
        SELECT d.* FROM documents d
        INNER JOIN document_favorites f ON f.document_id = d.id
        WHERE f.user_id = :uid AND d.is_deleted = 0
        ORDER BY d.id DESC
      ",
      'public' => "
        SELECT d.* FROM documents d
        WHERE d.is_public = 1 AND d.is_deleted = 0
        ORDER BY d.id DESC
      ",
      default => "
        SELECT d.* FROM documents d
        WHERE d.author_id = :uid AND d.is_deleted = 0
        ORDER BY d.id DESC
      ",
    };

    $st = $this->pdo->prepare($sql);
    if (str_contains($sql, ':uid')) {
      $st->execute([':uid' => $userId]);
    } else {
      $st->execute();
    }
    return $st->fetchAll() ?: [];
  }

  public function create(int $authorId, string $code, bool $isPublic): int
  {
    $st = $this->pdo->prepare("
      INSERT INTO documents(author_id, unique_slug, is_public, status, current_revision, code, is_deleted)
      VALUES(:author, NULL, :public, 'valid', 1, :code, 0)
    ");
    $st->execute([
      ':author' => $authorId,
      ':public' => $isPublic ? 1 : 0,
      ':code' => $code,
    ]);
    return (int)$this->pdo->lastInsertId();
  }

  public function delete(int $id): void
  {
    $st = $this->pdo->prepare("UPDATE documents SET is_deleted = 1, status = 'deleted' WHERE id = :id");
    $st->execute([':id' => $id]);
  }

  public function setPublic(int $id, bool $isPublic, ?string $slug): void
  {
    $st = $this->pdo->prepare("
      UPDATE documents
      SET is_public = :public, unique_slug = :slug
      WHERE id = :id
    ");
    $st->execute([
      ':id' => $id,
      ':public' => $isPublic ? 1 : 0,
      ':slug' => $slug,
    ]);
  }

  public function setFavorite(int $userId, int $documentId, bool $isFavorite): void
  {
    if ($isFavorite) {
      $st = $this->pdo->prepare("
        INSERT INTO document_favorites(user_id, document_id, ts_created)
        VALUES(:uid, :doc, datetime('now'))
        ON CONFLICT(user_id, document_id) DO NOTHING
      ");
      $st->execute([':uid' => $userId, ':doc' => $documentId]);
      return;
    }

    $st = $this->pdo->prepare("
      DELETE FROM document_favorites
      WHERE user_id = :uid AND document_id = :doc
    ");
    $st->execute([':uid' => $userId, ':doc' => $documentId]);
  }

  public function saveCode(int $id, string $code, int $newRevision, string $status): void
  {
    $st = $this->pdo->prepare("
      UPDATE documents
      SET code = :code, current_revision = :rev, status = :status
      WHERE id = :id
    ");
    $st->execute([
      ':id' => $id,
      ':code' => $code,
      ':rev' => $newRevision,
      ':status' => $status,
    ]);
  }

  public function getLatestRenderedSvgPath(int $documentId): ?string
  {
    $st = $this->pdo->prepare("
      SELECT svg_path FROM revisions
      WHERE document_id = :id AND svg_path IS NOT NULL
      ORDER BY revision DESC LIMIT 1
    ");
    $st->execute([':id' => $documentId]);
    $row = $st->fetch();
    return $row ? (string)$row['svg_path'] : null;
  }
}

final class PdoRevisionRepository implements RevisionRepository
{
  public function __construct(private PDO $pdo) {}

  public function create(int $documentId, int $revision, string $code, bool $isValid, ?string $svgPath): int
  {
    $st = $this->pdo->prepare("
      INSERT INTO revisions(document_id, revision, ts_created, code, is_valid, ts_rendered, svg_path)
      VALUES(:doc, :rev, datetime('now'), :code, :valid, datetime('now'), :svg)
    ");
    $st->execute([
      ':doc' => $documentId,
      ':rev' => $revision,
      ':code' => $code,
      ':valid' => $isValid ? 1 : 0,
      ':svg' => $svgPath,
    ]);
    return (int)$this->pdo->lastInsertId();
  }
}

final class PdoSessionRepository implements SessionRepository
{
  public function __construct(private PDO $pdo) {}

  public function ensure(int $documentId): int
  {
    $st = $this->pdo->prepare("
      INSERT INTO sessions(document_id, locked_by_user_id, lock_ts)
      VALUES(:doc, NULL, NULL)
      ON CONFLICT(document_id) DO NOTHING
    ");
    $st->execute([':doc' => $documentId]);

    $idSt = $this->pdo->prepare("SELECT id FROM sessions WHERE document_id = :doc");
    $idSt->execute([':doc' => $documentId]);
    $row = $idSt->fetch();
    if (!$row) {
      throw new \RuntimeException("Session row missing for document: $documentId");
    }
    return (int)$row['id'];
  }

  public function upsertLock(int $documentId, ?int $lockedByUserId): void
  {
    $this->ensure($documentId);
    $st = $this->pdo->prepare("
      UPDATE sessions
      SET locked_by_user_id = :uid, lock_ts = datetime('now')
      WHERE document_id = :doc
    ");
    $st->execute([
      ':doc' => $documentId,
      ':uid' => $lockedByUserId,
    ]);
  }

  public function getLock(int $documentId): ?int
  {
    $st = $this->pdo->prepare("SELECT locked_by_user_id FROM sessions WHERE document_id = :doc");
    $st->execute([':doc' => $documentId]);
    $row = $st->fetch();
    if (!$row) return null;
    return $row['locked_by_user_id'] === null ? null : (int)$row['locked_by_user_id'];
  }
}
