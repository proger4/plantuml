<?php

namespace App\Model;

class BaseProfile extends CActiveRecord
{
    public const TYPE_ADMIN = 'admin';
    public const TYPE_GUEST = 'guest';

    public function tableName(): string
    {
        return 'profiles';
    }

    public function scopes(): array
    {
        return [
            'active' => ['condition' => 't.is_active = 1'],
        ];
    }
}
