<?php
declare(strict_types=1);

namespace App\DocumentApplier;

use App\DocumentApplier\ApplierException;
use App\Domain\ValueObject\TextChange;
use App\DocumentApplier\StringOps;

/**
 * Validates a change against current code length.
 *
 * TODO (strict):
 * - enforce max document size
 * - enforce allowed characters if needed
 */
final class ChangeValidator
{
  public function validate(string $code, TextChange $change): void
  {
    $len = StringOps::len($code);
    $l = $change->range->left;
    $r = $change->range->right;

    if ($l < 0 || $r < 0 || $l > $r || $r > $len) {
      throw new ApplierException("Range out of bounds: [$l,$r] len=$len");
    }
  }
}
