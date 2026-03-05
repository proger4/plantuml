<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum RestrictionCategory: string
{
  case not_implemented = 'not_implemented';
  case auth_required = 'auth_required';
  case not_owner = 'not_owner';
  case locked_by_other = 'locked_by_other';
  case deleted = 'deleted';
  case invalid_transition = 'invalid_transition';
  case forbidden = 'forbidden';
}
