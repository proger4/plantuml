<?php

declare(strict_types=1);

namespace Tools\Usage;

final class UsagePatterns
{
    /** @return array<string,list<string>> */
    public static function all(): array
    {
        return [
            'model_refs' => [
                '/::model\s*\(/',
                '/new\s+[A-Za-z_][A-Za-z0-9_\\\\]*\s*\(/',
                '/instanceof\s+[A-Za-z_][A-Za-z0-9_\\\\]*/',
                '/use\s+[A-Za-z_][A-Za-z0-9_\\\\]+;/',
            ],
            'singleton' => [
                '/->user\b/',
                '/->user->/',
                '/isset\s*\([^)]*->user\)/',
                '/empty\s*\([^)]*->user\)/',
            ],
            'collection' => [
                '/foreach\s*\([^)]*->members\s+as/',
                '/count\s*\([^)]*->members\)/',
                '/->members\s*\[/',
                '/array_map\s*\([^)]*->members/',
            ],
            'string_relation' => [
                '/with\s*\(\s*[\'\"]user[\'\"]\s*\)/',
                '/with\s*\(\s*array\s*\(\s*[\'\"]user[\'\"]/',
                '/criteria->with/',
            ],
            'criteria' => [
                '/CDbCriteria/',
                '/->join\b/',
                '/->condition\b/',
                '/->compare\b/',
                '/->group\b/',
                '/->having\b/',
                '/->order\b/',
                '/->alias\b/',
            ],
            'inheritance' => [
                '/instanceof\s+[A-Za-z_][A-Za-z0-9_\\\\]*/',
                '/switch\s*\([^)]*->type\)/',
                '/\bTYPE_[A-Z0-9_]+\b/',
                '/createByType\s*\(/',
            ],
            'getter_conflict' => [
                '/->user\b/',
                '/function\s+getUser\s*\(/',
            ],
        ];
    }
}
