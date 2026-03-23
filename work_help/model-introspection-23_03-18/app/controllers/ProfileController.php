<?php

namespace App\Controller;

use App\Model\Profile;
use CDbCriteria;

final class ProfileController
{
    public function actionIndex(): void
    {
        $profile = Profile::model()->findByPk(7);

        // Collection-like usage for BELONGS_TO relation "user" => conflict signal.
        foreach ($profile->user as $u) {
            echo $u->id;
        }

        if (isset($profile->user)) {
            echo 'has-user';
        }

        // Mixed usage patterns for "members".
        foreach ($profile->members as $m) {
            echo $m->id;
        }
        $first = $profile->members[0] ?? null;
        if (!empty($profile->members)) {
            echo count($profile->members);
        }
        array_map(static fn ($x) => $x->id, $profile->members);

        $criteria = new CDbCriteria();
        $criteria->with = ['user'];
        $criteria->join = 'LEFT JOIN users u ON u.id = t.user_id';
        $criteria->condition = 't.is_active = 1';
        $criteria->group = 't.id';
        $criteria->having = 'COUNT(t.id) > 0';
        $criteria->order = 't.id DESC';
        $criteria->alias = 't';

        switch ($profile->type) {
            case Profile::TYPE_ADMIN:
                echo 'admin';
                break;
            case Profile::TYPE_GUEST:
                echo 'guest';
                break;
        }
    }
}
