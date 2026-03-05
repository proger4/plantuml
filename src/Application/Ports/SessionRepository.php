<?php
declare(strict_types=1);

namespace App\Application\Ports;

/**
 * SessionRepository — порт для хранения состояния сессии/lock в БД.
 * (Присутствие в WS хранится в памяти SessionManager, а lock — в SQLite, чтобы пережить перезапуск WS/HTTP.)
 */
interface SessionRepository
{
    /**
     * Ensure row exists for document session and return session id.
     */
    public function ensure(int $documentId): int;

    /**
     * Set/unset lock owner for a document.
     * - $lockedByUserId = null => unlock
     */
    public function upsertLock(int $documentId, ?int $lockedByUserId): void;

    /**
     * @return int|null userId owning the lock, or null when unlocked / missing.
     */
    public function getLock(int $documentId): ?int;
}
