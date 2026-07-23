<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable stored room-type catalogue record.
 */
final readonly class RoomType
{
    /**
     * Creates a passive room type from validated persistence data.
     */
    public function __construct(
        private int $id,
        private string $name,
        private string $slug,
        private ?string $description,
        private string $basePrice,
        private int $capacityAdults,
        private int $capacityChildren,
        private bool $active,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $name === ''
            || $slug === ''
            || !$this->isPositiveMoney($basePrice)
            || $capacityAdults < 1
            || $capacityChildren < 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Room type data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
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

    /**
     * Returns the exact stored NPR decimal without floating-point conversion.
     */
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

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
