<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RoomImage;

interface RoomImageCatalogRepository
{
    public function countByRoomId(int $roomId): int;

    /** @return list<RoomImage> */
    public function findAllByRoomId(int $roomId): array;

    public function findPrimaryByRoomId(int $roomId): ?RoomImage;

    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage;

    public function create(
        int $roomId,
        string $filePath,
        ?string $altText,
        int $sortOrder,
        bool $primary,
    ): RoomImage;

    public function clearPrimaryByRoomId(int $roomId): void;

    public function updateMetadata(
        int $imageId,
        int $roomId,
        ?string $altText,
        int $sortOrder,
    ): ?RoomImage;

    public function setPrimary(int $imageId, int $roomId): ?RoomImage;

    public function delete(int $imageId, int $roomId): ?RoomImage;
}
