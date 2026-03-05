<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum ChangeType: string
{
  case insert = 'insert';
  case replace = 'replace';
}
