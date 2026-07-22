<?php

declare(strict_types=1);

namespace App\Requests;

/**
 * Holds one validated physical-room create or update request.
 */
final readonly class RoomUpsertRequest
{
    /**
     * @param list<int> $amenityIds
     */
    private function __construct(
        private int $roomTypeId,
        private string $roomNumber,
        private string $name,
        private string $slug,
        private ?string $description,
        private string $pricePerNight,
        private int $capacityAdults,
        private int $capacityChildren,
        private string $operationalStatus,
        private bool $published,
        private array $amenityIds,
    ) {
    }

    public static function fromInput(
        mixed $roomTypeId,
        mixed $roomNumber,
        mixed $name,
        mixed $slug,
        mixed $description,
        mixed $pricePerNight,
        mixed $capacityAdults,
        mixed $capacityChildren,
        mixed $operationalStatus,
        mixed $published,
        mixed $amenityIds,
    ): self {
        return new self(
            RoomCatalogInput::identifier($roomTypeId, 'Room type'),
            RoomCatalogInput::requiredText($roomNumber, 'Room number', 30),
            RoomCatalogInput::requiredText($name, 'Name', 100),
            RoomCatalogInput::slug($slug),
            RoomCatalogInput::optionalDescription($description),
            RoomCatalogInput::money($pricePerNight, 'Price per night'),
            RoomCatalogInput::integer($capacityAdults, 'Adult capacity', 1),
            RoomCatalogInput::integer($capacityChildren, 'Child capacity', 0),
            RoomCatalogInput::operationalStatus($operationalStatus),
            RoomCatalogInput::explicitBoolean($published, 'Published state'),
            RoomCatalogInput::identifierList($amenityIds, 'Amenity'),
        );
    }

    public function roomTypeId(): int
    {
        return $this->roomTypeId;
    }

    public function roomNumber(): string
    {
        return $this->roomNumber;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function pricePerNight(): string
    {
        return $this->pricePerNight;
    }

    public function capacityAdults(): int
    {
        return $this->capacityAdults;
    }

    public function capacityChildren(): int
    {
        return $this->capacityChildren;
    }

    public function operationalStatus(): string
    {
        return $this->operationalStatus;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    /** @return list<int> */
    public function amenityIds(): array
    {
        return $this->amenityIds;
    }
}
