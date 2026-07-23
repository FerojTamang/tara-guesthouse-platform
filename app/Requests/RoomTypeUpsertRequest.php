<?php

declare(strict_types=1);

namespace App\Requests;

/**
 * Holds one validated room-type create or update request.
 */
final readonly class RoomTypeUpsertRequest
{
    private function __construct(
        private string $name,
        private string $slug,
        private ?string $description,
        private string $basePrice,
        private int $capacityAdults,
        private int $capacityChildren,
        private bool $active,
    ) {
    }

    public static function fromInput(
        mixed $name,
        mixed $slug,
        mixed $description,
        mixed $basePrice,
        mixed $capacityAdults,
        mixed $capacityChildren,
        mixed $active,
    ): self {
        return new self(
            RoomCatalogInput::requiredText($name, 'Name', 100),
            RoomCatalogInput::slug($slug),
            RoomCatalogInput::optionalDescription($description),
            RoomCatalogInput::money($basePrice),
            RoomCatalogInput::integer($capacityAdults, 'Adult capacity', 1),
            RoomCatalogInput::integer($capacityChildren, 'Child capacity', 0),
            RoomCatalogInput::explicitBoolean($active, 'Active state'),
        );
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

    public function basePrice(): string
    {
        return $this->basePrice;
    }

    public function capacityAdults(): int
    {
        return $this->capacityAdults;
    }

    public function capacityChildren(): int
    {
        return $this->capacityChildren;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
