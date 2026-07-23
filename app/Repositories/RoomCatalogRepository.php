<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Room;
use App\Models\Amenity;

/**
 * Defines the persistence operations required by physical-room administration.
 */
interface RoomCatalogRepository
{
    /**
     * Locks one current room inside an already active transaction.
     *
     * Returns null when the identifier is invalid or the room is missing or soft deleted.
     */
    public function lockCurrentById(int $roomId): ?Room;

    public function findById(int $id): ?Room;

    /** @return list<Room> */
    public function findAll(): array;

    /** @return list<Room> */
    public function findAllPublic(): array;

    public function findPublicBySlug(string $slug): ?Room;

    /** @return list<Amenity> */
    public function findActiveAmenitiesByRoomId(int $roomId): array;

    /** @return list<int> */
    public function findAmenityIds(int $roomId): array;

    public function create(
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
    ): Room;

    public function update(
        int $id,
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
    ): ?Room;

    /** @param list<int> $amenityIds */
    public function replaceAmenities(int $roomId, array $amenityIds): void;

    public function softDelete(int $id): ?Room;
}
