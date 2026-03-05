<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum DocumentStatus: string
{
  case empty = 'empty';
  case valid = 'valid';
  case invalid = 'invalid';
  case deleted = 'deleted';
}
