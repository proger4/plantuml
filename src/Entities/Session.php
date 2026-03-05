<?php
declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  public ?int $id = null;

  #[ORM\Column(type: 'integer', name: 'document_id', unique: true)]
  public int $documentId;

  #[ORM\Column(type: 'integer', nullable: true, name: 'locked_by_user_id')]
  public ?int $lockedByUserId = null;

  #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'lock_ts')]
  public ?\DateTimeImmutable $lockTs = null;
}
