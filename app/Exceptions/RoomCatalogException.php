<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents one safe room-catalogue administration failure.
 */
final class RoomCatalogException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function duplicateRoomTypeSlug(?Throwable $previous = null): self
    {
        return new self('A room type with this slug already exists.', 'conflict', $previous);
    }

    public static function duplicateAmenityName(?Throwable $previous = null): self
    {
        return new self('An amenity with this name already exists.', 'conflict', $previous);
    }

    public static function duplicateRoomNumber(?Throwable $previous = null): self
    {
        return new self('A room with this room number already exists.', 'conflict', $previous);
    }

    public static function duplicateRoomSlug(?Throwable $previous = null): self
    {
        return new self('A room with this slug already exists.', 'conflict', $previous);
    }

    public static function roomTypeNotFound(): self
    {
        return new self('The requested room type was not found.', 'not_found');
    }

    public static function amenityNotFound(): self
    {
        return new self('The requested amenity was not found.', 'not_found');
    }

    public static function roomNotFound(): self
    {
        return new self('The requested room was not found.', 'not_found');
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Room catalogue operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isConflict(): bool
    {
        return $this->category === 'conflict';
    }

    public function isNotFound(): bool
    {
        return $this->category === 'not_found';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }
}
