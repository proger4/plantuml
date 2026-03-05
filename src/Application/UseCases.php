<?php
declare(strict_types=1);

namespace App\Application;

use App\Collaborations\SessionManager;
use App\DocumentApplier\DocumentApplier;
use App\DocumentApplier\ApplierException;
use App\Domain\Enum\ChangeType;
use App\Domain\Enum\DocumentStatus;
use App\Domain\Policy\RestrictionInterface;
use App\Domain\ValueObject\CaretRange;
use App\Domain\ValueObject\TextChange;

/**
 * UseCases facade: keeps file count low, still preserves clean boundaries.
 *
 * TODO (later):
 * - Split into per-usecase classes (u1..u8) if you want strict Clean Architecture.
 * - Add DTOs / presenters.
 */
final class UseCases
{
  public function __construct(
    private Ports $ports,
    private RestrictionInterface $restrictions,
    private SessionManager $sessionManager,
    private DocumentApplier $applier,
  ) {}

  public function getDocument(int $userId, int $docId): array
  {
    $doc = $this->ports->documents->getById($docId);
    $this->assertAllowed($this->restrictions->canView($userId, $doc), 'forbidden');
    return $doc;
  }

  public function getDocumentWithPreview(int $userId, int $docId): array
  {
    $doc = $this->getDocument($userId, $docId);
    $svgPath = $this->ports->documents->getLatestRenderedSvgPath($docId);
    $svg = null;
    if (is_string($svgPath) && $svgPath !== '' && is_file($svgPath)) {
      $content = @file_get_contents($svgPath);
      if ($content !== false) {
        $svg = $content;
      }
    }

    return [
      'document' => $doc,
      'preview' => [
        'svgPath' => $svgPath,
        'svg' => $svg,
      ],
    ];
  }

  public function joinSession(int $userId, int $docId): array
  {
    $doc = $this->ports->documents->getById($docId);
    $this->assertAllowed($this->restrictions->canView($userId, $doc), 'forbidden');

    $sessionId = $this->ports->sessions->ensure($docId);
    return [
      'sessionId' => $sessionId,
      'wsUrl' => 'ws://127.0.0.1:8081',
    ];
  }

  public function listDocuments(int $userId, string $filter): array
  {
    $normalized = in_array($filter, ['personal', 'favorites', 'public'], true) ? $filter : 'personal';
    return $this->ports->documents->listByFilter($userId, $normalized);
  }

  public function createDocument(int $userId, string $code, bool $isPublic): array
  {
    if ($userId <= 0) {
      throw new \RuntimeException('Auth required');
    }
    $docId = $this->ports->documents->create($userId, $code, $isPublic);
    $this->ports->sessions->ensure($docId);
    return $this->ports->documents->getById($docId);
  }

  public function deleteDocument(int $userId, int $docId): void
  {
    $doc = $this->ports->documents->getById($docId);
    if ((int)$doc['author_id'] !== $userId) {
      throw new \RuntimeException('Forbidden');
    }
    $this->ports->documents->delete($docId);
  }

  public function setFavorite(int $userId, int $docId, bool $isFavorite): void
  {
    $this->ports->documents->getById($docId);
    $this->ports->documents->setFavorite($userId, $docId, $isFavorite);
  }

  public function publishDocument(int $userId, int $docId, bool $isPublic, ?string $slug): array
  {
    $doc = $this->ports->documents->getById($docId);
    if ((int)$doc['author_id'] !== $userId) {
      throw new \RuntimeException('Forbidden');
    }
    $effectiveSlug = $isPublic ? ($slug ?: 'doc-' . $docId) : null;
    $this->ports->documents->setPublic($docId, $isPublic, $effectiveSlug);
    return $this->ports->documents->getById($docId);
  }

  public function getLockUserId(int $docId): ?int
  {
    return $this->ports->sessions->getLock($docId);
  }

  public function acquireLock(int $userId, int $docId): void
  {
    $current = $this->ports->sessions->getLock($docId);
    $this->assertAllowed($this->restrictions->canAcquireLock($userId, $docId, $current));
    // TODO: add lease timeout.
    $this->ports->sessions->upsertLock($docId, $userId);
  }

  public function releaseLock(int $userId, int $docId): void
  {
    $lock = $this->ports->sessions->getLock($docId);
    if ($lock !== null && $lock !== $userId) {
      throw new \RuntimeException('Locked by other');
    }
    $this->ports->sessions->upsertLock($docId, null);
  }

  /**
   * Minimal WS edit apply.
   * Payload shape (from WS):
   *   change: { type:'replace'|'insert', range:{left,right}, text:string }
   *   caret:  { left,right }
   */
  public function applyEdit(int $userId, int $docId, array $payload): array
  {
    $doc = $this->ports->documents->getById($docId);
    $lockUserId = $this->ports->sessions->getLock($docId);

    [$ok, $cat, $msg] = $this->restrictions->canEdit($userId, $docId, $lockUserId);
    if (!$ok) {
      return [
        'ok' => false,
        'error' => ['category' => (string)$cat?->value, 'message' => $msg],
      ];
    }

    $ch = $payload['change'] ?? null;
    if (!is_array($ch)) {
      return ['ok' => false, 'error' => ['category' => 'bad_request', 'message' => 'change required']];
    }

    try {
      $type = ChangeType::from((string)($ch['type'] ?? 'replace'));
      $range = $ch['range'] ?? ['left' => 0, 'right' => 0];
      $left = (int)($range['left'] ?? 0);
      $right = (int)($range['right'] ?? $left);
      $text = (string)($ch['text'] ?? '');
    } catch (\ValueError $e) {
      return [
        'ok' => false,
        'error' => ['category' => 'invalid_transition', 'message' => $e->getMessage()],
      ];
    }

    try {
      $change = new TextChange($type, new CaretRange($left, $right), $text);
      $applied = $this->applier->apply($doc['code'], $change);
    } catch (ApplierException $e) {
      // Full-document replace is the transport format in current UI.
      // If right bound is stale, normalize to current server code length.
      if ($type === ChangeType::replace && $left === 0) {
        try {
          $safeRight = mb_strlen((string)$doc['code'], 'UTF-8');
          $fallback = new TextChange($type, new CaretRange(0, $safeRight), $text);
          $applied = $this->applier->apply($doc['code'], $fallback);
        } catch (\Throwable $fallbackError) {
          return [
            'ok' => false,
            'error' => ['category' => 'invalid_transition', 'message' => $fallbackError->getMessage()],
          ];
        }
      } else {
        return [
          'ok' => false,
          'error' => ['category' => 'invalid_transition', 'message' => $e->getMessage()],
        ];
      }
    }

    $newRev = ((int)$doc['current_revision']) + 1;
    $this->ports->documents->saveCode($docId, $applied->code, $newRev, DocumentStatus::valid->value);

    $this->ports->events->emit('DOC_EDIT_APPLIED', [
      'docId' => $docId,
      'userId' => $userId,
      'revision' => $newRev,
    ]);

    return [
      'ok' => true,
      'docId' => $docId,
      'revision' => $newRev,
      'caret' => ['left' => $applied->caretLeft, 'right' => $applied->caretRight],
    ];
  }

  /**
   * Save revision + render (HTTP endpoint).
   */
  public function saveRevisionAndRender(int $userId, int $docId, string $code): array
  {
    $doc = $this->ports->documents->getById($docId);
    $lockUserId = $this->ports->sessions->getLock($docId);
    $this->assertAllowed($this->restrictions->canSaveRevision($userId, $doc, $lockUserId), 'forbidden');

    $newRev = ((int)$doc['current_revision']) + 1;
    $render = $this->ports->renderer->renderSvg($docId, $newRev, $code);

    $this->ports->documents->saveCode($docId, $code, $newRev, DocumentStatus::valid->value);
    $revisionId = $this->ports->revisions->create($docId, $newRev, $code, (bool)$render['isValid'], (string)$render['svgPath']);

    $this->ports->events->emit('DOC_RENDER_FINISHED', [
      'docId' => $docId,
      'revision' => $newRev,
      'svgPath' => $render['svgPath'],
    ]);

    return [
      'ok' => true,
      'docId' => $docId,
      'revisionId' => $revisionId,
      'revision' => $newRev,
      'isValid' => (bool)$render['isValid'],
      'svgPath' => (string)$render['svgPath'],
      'svg' => (string)$render['svg'],
    ];
  }

  public function renderLatest(int $userId, int $docId): array
  {
    $doc = $this->ports->documents->getById($docId);
    $rev = (int)$doc['current_revision'];
    $render = $this->ports->renderer->renderSvg($docId, $rev, (string)$doc['code']);

    return [
      'docId' => $docId,
      'revision' => $rev,
      'isValid' => (bool)$render['isValid'],
      'svgPath' => (string)$render['svgPath'],
      'svg' => (string)$render['svg'],
    ];
  }

  private function assertAllowed(array $decision, string $defaultError = 'forbidden'): void
  {
    [$ok, $cat] = $decision + [false, null, null];
    if ($ok) return;
    $code = $cat?->value ?? $defaultError;
    throw new \RuntimeException($code);
  }
}
