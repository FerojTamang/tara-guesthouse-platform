<?php

declare(strict_types=1);

namespace App\Requests;

/**
 * Holds one validated amenity create or update request.
 */
final readonly class AmenityUpsertRequest
{
    private function __construct(
        private string $name,
        private ?string $icon,
        private bool $active,
    ) {
    }

    public static function fromInput(mixed $name, mixed $icon, mixed $active): self
    {
        return new self(
            RoomCatalogInput::requiredText($name, 'Name', 100),
            RoomCatalogInput::optionalIcon($icon),
            RoomCatalogInput::explicitBoolean($active, 'Active state'),
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
