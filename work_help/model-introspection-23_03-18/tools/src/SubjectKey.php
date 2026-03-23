<?php

declare(strict_types=1);

namespace Tools;

final class SubjectKey
{
    public static function caseType(string $subjectKey): string
    {
        if (str_ends_with($subjectKey, '::discriminator')) {
            return 'DISCRIMINATOR_CASE';
        }
        if (str_contains($subjectKey, '::')) {
            return 'RELATION_CASE';
        }
        return 'INHERITANCE_CASE';
    }

    public static function rootClass(string $subjectKey): string
    {
        $parts = explode('::', $subjectKey, 2);
        return $parts[0];
    }

    public static function relationName(string $subjectKey): ?string
    {
        if (!str_contains($subjectKey, '::')) {
            return null;
        }
        $parts = explode('::', $subjectKey, 2);
        if ($parts[1] === 'discriminator') {
            return null;
        }
        return $parts[1];
    }

    public static function safeName(string $subjectKey): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]+/', '_', $subjectKey) ?? 'subject';
    }
}
