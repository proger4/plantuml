<?php
declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'quizzes')]
class Quiz
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  public ?int $id = null;

  #[ORM\Column(type: 'text')]
  public string $formulation;

  #[ORM\Column(type: 'integer', name: 'before_document_id')]
  public int $beforeDocumentId;

  #[ORM\Column(type: 'integer', name: 'required_document_id')]
  public int $requiredDocumentId;
}
