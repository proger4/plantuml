<?php
declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Enum\ChangeType;
use App\Domain\ValueObject\CaretRange;

final class TextChange
{
  public function __construct(
    public readonly ChangeType $type,
    public readonly CaretRange $range,
    public readonly string $text
  ) {}
}
