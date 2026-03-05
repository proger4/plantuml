<?php
declare(strict_types=1);

namespace App\DocumentApplier;

/**
 * UTF-8 safe string ops.
 * TODO (later):
 * - consider grapheme cluster handling if you need perfect caret math (Intl).
 */
final class StringOps
{
  public static function len(string $s): int
  {
    return mb_strlen($s, 'UTF-8');
  }

  public static function slice(string $s, int $start, ?int $length = null): string
  {
    if ($length === null) {
      return mb_substr($s, $start, null, 'UTF-8');
    }
    return mb_substr($s, $start, $length, 'UTF-8');
  }
}
