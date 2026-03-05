<?php
declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'documents')]
class Document
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  public ?int $id = null;

  #[ORM\Column(type: 'integer', name: 'author_id')]
  public int $authorId;

  #[ORM\Column(type: 'string', nullable: true, name: 'unique_slug')]
  public ?string $uniqueSlug = null;

  #[ORM\Column(type: 'boolean', name: 'is_public')]
  public bool $isPublic = false;

  #[ORM\Column(type: 'string')]
  public string $status = 'valid';

  #[ORM\Column(type: 'integer', name: 'current_revision')]
  public int $currentRevision = 1;

  #[ORM\Column(type: 'text')]
  public string $code;

  #[ORM\Column(type: 'boolean', name: 'is_deleted')]
  public bool $isDeleted = false;
}
