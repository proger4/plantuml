<?php
declare(strict_types=1);

namespace App\Application\Ports;

/**
 * RevisionRepository — порт для истории ревизий.
 */
interface RevisionRepository
{
    /**
     * Create a revision record. Returns inserted revision row id.
     */
    public function create(int $documentId, int $revision, string $code, bool $isValid, ?string $svgPath): int;
}