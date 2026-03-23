<?php

namespace App\Model;

class User extends CActiveRecord
{
    public static function model(string $className = __CLASS__): self
    {
        return new self();
    }

    public function tableName(): string
    {
        return 'users';
    }
}
