<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable stored physical-room record.
 */
final readonly class Room
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_INACTIVE = 'inactive';

    private const OPERATIONAL_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_INACTIVE,
    ];

    /**
     * Creates a passive room from validated persistence data.
     */
    public function __construct(
        private int $id,
        private RoomType $roomType,
        private string $roomNumber,
        private string $name,
        private string $slug,
        private ?string $description,
        private string $pricePerNight,
        private int $capacityAdults,
        private int $capacityChildren,
        private string $operationalStatus,
        private bool $published,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {
        if (
            $id <= 0
            || $roomNumber === ''
            || $name === ''
            || $slug === ''
            || !$this->isPositiveMoney($pricePerNight)
            || $capacityAdults < 1
            || $capacityChildren < 0
            || !in_array($operationalStatus, self::OPERATIONAL_STATUSES, true)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
            || ($deletedAt !== null && $deletedAt->getOffset() !== 0)
        ) {
            throw new InvalidArgumentException('Room data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function roomType(): RoomType
    {
        return $this->roomType;
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

    /**
     * Returns the exact stored NPR decimal without floating-point conversion.
     */
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

    public function isActive(): bool
    {
        return $this->operationalStatus === self::STATUS_ACTIVE;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->operationalStatus === self::STATUS_MAINTENANCE;
    }

    public function isInactive(): bool
    {
        return $this->operationalStatus === self::STATUS_INACTIVE;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
