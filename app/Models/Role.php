<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable stored application role.
 */
final readonly class Role
{
    public const ADMINISTRATOR = 'Administrator';

    /**
     * Creates a passive role from validated persistence data.
     */
    public function __construct(
        private int $id,
        private string $name,
        private ?string $description,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $name === ''
            || $name !== trim($name)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Role data is invalid.');
        }
    }

    /**
     * Returns the stored role identifier.
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Returns the stored role name without normalization.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the optional stored role description.
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Returns the role creation timestamp.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns the role update timestamp.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Determines whether this is the one approved Version 1 role.
     */
    public function isAdministrator(): bool
    {
        return $this->name === self::ADMINISTRATOR;
    }
}
