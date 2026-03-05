<?php
declare(strict_types=1);

namespace App\Application\Ports;

/**
 * DocumentRepository — порт доступа к документам (Document aggregate root).
 * Возвращает "row" (ассок массив) в скелете; позже можно заменить на DTO/Entity.
 */
interface DocumentRepository
{
    /**
     * @return array{
     *   id:int,
     *   author_id:int,
     *   unique_slug:?string,
     *   is_public:int,
     *   status:string,
     *   current_revision:int,
     *   code:string,
     *   is_deleted:int
     * }
     */
    public function getById(int $id): array;
    public function listByFilter(int $userId, string $filter): array;
    public function create(int $authorId, string $code, bool $isPublic): int;
    public function delete(int $id): void;
    public function setPublic(int $id, bool $isPublic, ?string $slug): void;
    public function setFavorite(int $userId, int $documentId, bool $isFavorite): void;

    /**
     * Persist new code + revision + status for document.
     * Это используется WS-правками и сохранением ревизий.
     */
    public function saveCode(int $id, string $code, int $newRevision, string $status): void;

    /**
     * Convenience read for preview (optional).
     */
    public function getLatestRenderedSvgPath(int $documentId): ?string;
}
