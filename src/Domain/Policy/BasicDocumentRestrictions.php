<?php
declare(strict_types=1);

namespace App\Domain\Policy;

use App\Application\Ports\DocumentRepository;
use App\Domain\Enum\RestrictionCategory;
use App\Domain\Policy\RestrictionInterface;

/**
 * Minimal restrictions/policies (DDD-light).
 *
 * TODO (strict):
 * - Owner/public visibility rules
 * - Deleted status guard
 * - Lock ownership rules for WS edits
 * - Publish transitions (Workflow)
 */
final class BasicDocumentRestrictions implements RestrictionInterface
{
  public function __construct(private DocumentRepository $docs) {}

  public function canEdit(int $userId, int $documentId, ?int $lockUserId): array
  {
    if ($userId <= 0) {
      return [false, RestrictionCategory::auth_required, 'Auth required'];
    }
    if ($lockUserId !== null && $lockUserId !== $userId) {
      return [false, RestrictionCategory::locked_by_other, 'Locked by another user'];
    }
    return [true, null, null];
  }

  public function canAcquireLock(int $userId, int $documentId, ?int $lockUserId): array
  {
    if ($userId <= 0) {
      return [false, RestrictionCategory::auth_required, 'Auth required'];
    }
    if ($lockUserId !== null && $lockUserId !== $userId) {
      return [false, RestrictionCategory::locked_by_other, 'Locked by another user'];
    }
    return [true, null, null];
  }

  public function canView(int $userId, array $documentRow): array
  {
    if ((int)$documentRow['is_deleted'] === 1) {
      return [false, RestrictionCategory::deleted, 'Document deleted'];
    }
    if ((int)$documentRow['is_public'] === 1) {
      return [true, null, null];
    }
    if ((int)$documentRow['author_id'] === $userId) {
      return [true, null, null];
    }
    return [false, RestrictionCategory::forbidden, 'Not allowed'];
  }

  public function canSaveRevision(int $userId, array $documentRow, ?int $lockUserId): array
  {
    [$canView, $cat, $msg] = $this->canView($userId, $documentRow);
    if (!$canView) {
      return [$canView, $cat, $msg];
    }
    if ($userId <= 0) {
      return [false, RestrictionCategory::auth_required, 'Auth required'];
    }
    if ($lockUserId !== null && $lockUserId !== $userId) {
      return [false, RestrictionCategory::locked_by_other, 'Locked by another user'];
    }
    return [true, null, null];
  }
}
