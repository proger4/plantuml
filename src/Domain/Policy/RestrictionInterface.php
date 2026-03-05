<?php
declare(strict_types=1);

namespace App\Domain\Policy;

use App\Domain\Enum\RestrictionCategory;

interface RestrictionInterface
{
  /**
   * Return [allowed, category, message].
   * TODO (later): replace with dedicated Result object.
   */
  public function canEdit(int $userId, int $documentId, ?int $lockUserId): array;

  public function canView(int $userId, array $documentRow): array;

  /**
   * Lock rules in one place (no ad-hoc checks in use cases).
   */
  public function canAcquireLock(int $userId, int $documentId, ?int $lockUserId): array;

  /**
   * Save/commit rules for HTTP revision endpoint.
   */
  public function canSaveRevision(int $userId, array $documentRow, ?int $lockUserId): array;
}
