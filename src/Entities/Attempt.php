<?php
declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'attempts')]
class Attempt
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  public ?int $id = null;

  #[ORM\Column(type: 'integer', name: 'user_id')]
  public int $userId;

  #[ORM\Column(type: 'integer', name: 'quiz_id')]
  public int $quizId;

  #[ORM\Column(type: 'integer', name: 'tryout_revision_id')]
  public int $tryoutRevisionId;

  #[ORM\Column(type: 'datetime_immutable', name: 'ts_created')]
  public \DateTimeImmutable $tsCreated;

  #[ORM\Column(type: 'integer')]
  public int $score = 0;
}
