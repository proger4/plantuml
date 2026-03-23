<?php

namespace App\Service;

use App\Model\AdminProfile;
use App\Model\BaseProfile;
use App\Model\GuestProfile;

final class ProfileFactory
{
    public static function createByType(string $type): BaseProfile
    {
        if ($type === BaseProfile::TYPE_ADMIN) {
            return new AdminProfile();
        }
        return new GuestProfile();
    }
}
