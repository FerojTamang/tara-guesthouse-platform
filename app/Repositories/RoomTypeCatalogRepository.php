<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RoomType;

/**
 * Defines the persistence operations required by room-type administration.
 */
interface RoomTypeCatalogRepository
{
    public function findById(int $id): ?RoomType;

    /** @return list<RoomType> */
    public function findAll(): array;

    /** @return list<RoomType> */
    public function findAllActive(): array;

    public function create(
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): RoomType;

    public function update(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): ?RoomType;

    public function setActive(int $id, bool $active): ?RoomType;
}
