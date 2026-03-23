<?php

namespace App\Model;

class Profile extends BaseProfile
{
    public static function model(string $className = __CLASS__): self
    {
        return new self();
    }

    public function relations(): array
    {
        return [
            // Intentionally problematic for testing:
            // BELONGS_TO but usage will look collection-like.
            'user' => array(self::BELONGS_TO, 'App\\Model\\User', 'user_id'),

            // HAS_MANY with sparse SQL support.
            'members' => array(self::HAS_MANY, 'App\\Model\\Member', 'profile_id'),

            // Explicitly unsupported-ish relation styles for DROP classification.
            'tags' => array(self::MANY_MANY, 'App\\Model\\Tag', 'profile_tag(profile_id, tag_id)'),
            'statViews' => array(self::STAT, 'App\\Model\\View', 'profile_id'),
        ];
    }
}
