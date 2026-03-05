<?php
declare(strict_types=1);

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  public ?int $id = null;

  #[ORM\Column(type: 'string', unique: true)]
  public string $name;

  #[ORM\Column(type: 'string', name: 'password_hash')]
  public string $passwordHash;
}
