<?php
declare(strict_types=1);

namespace App\Domain\ValueObject;

final class CaretRange
{
  public function __construct(
    public readonly int $left,
    public readonly int $right
  ) {
    if ($left < 0 || $right < 0 || $left > $right) {
      throw new \InvalidArgumentException('Invalid caret range');
    }
  }
}
