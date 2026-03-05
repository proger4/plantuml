<?php
declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'revisions')]
class Revision
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  public ?int $id = null;

  #[ORM\Column(type: 'integer', name: 'document_id')]
  public int $documentId;

  #[ORM\Column(type: 'integer')]
  public int $revision;

  #[ORM\Column(type: 'datetime_immutable', name: 'ts_created')]
  public \DateTimeImmutable $tsCreated;

  #[ORM\Column(type: 'text')]
  public string $code;

  #[ORM\Column(type: 'boolean', name: 'is_valid')]
  public bool $isValid = true;

  #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'ts_rendered')]
  public ?\DateTimeImmutable $tsRendered = null;

  #[ORM\Column(type: 'string', nullable: true, name: 'svg_path')]
  public ?string $svgPath = null;
}
