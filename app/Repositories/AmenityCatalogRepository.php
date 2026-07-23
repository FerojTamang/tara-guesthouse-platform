<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Amenity;

/**
 * Defines the persistence operations required by amenity administration.
 */
interface AmenityCatalogRepository
{
    public function findById(int $id): ?Amenity;

    /** @return list<Amenity> */
    public function findAll(): array;

    /** @return list<Amenity> */
    public function findAllActive(): array;

    public function create(string $name, ?string $icon, bool $active): Amenity;

    public function update(int $id, string $name, ?string $icon, bool $active): ?Amenity;

    public function setActive(int $id, bool $active): ?Amenity;
}
