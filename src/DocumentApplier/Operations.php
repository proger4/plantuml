<?php
declare(strict_types=1);

namespace App\DocumentApplier;

use App\Domain\Enum\ChangeType;
use App\Domain\ValueObject\TextChange;

final class Operations
{
  public static function apply(string $code, TextChange $change): string
  {
    $l = $change->range->left;
    $r = $change->range->right;

    $before = StringOps::slice($code, 0, $l);
    $after  = StringOps::slice($code, $r);

    return match ($change->type) {
      ChangeType::insert => $before . $change->text . $after,
      ChangeType::replace => $before . $change->text . $after,
    };
  }
}
