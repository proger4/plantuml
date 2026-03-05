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
}
