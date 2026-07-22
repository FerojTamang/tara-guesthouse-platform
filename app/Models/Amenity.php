<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable stored amenity catalogue record.
 */
final readonly class Amenity
{
    /**
     * Creates a passive amenity from validated persistence data.
     */
    public function __construct(
        private int $id,
        private string $name,
        private ?string $icon,
        private bool $active,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $name === ''
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Amenity data is invalid.');
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

    public function icon(): ?string
    {
        return $this->icon;
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
}
