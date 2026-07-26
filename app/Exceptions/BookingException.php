<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class BookingException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function validation(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function roomNotFound(): self
    {
        return new self('The selected room was not found.', 'not_found');
    }

    public static function roomUnavailable(): self
    {
        return new self('The selected room is not available for this stay.', 'conflict');
    }

    public static function duplicateReference(?Throwable $previous = null): self
    {
        return new self('The booking reference is already in use.', 'duplicate_reference', $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Booking operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isNotFound(): bool
    {
        return $this->category === 'not_found';
    }

    public function isConflict(): bool
    {
        return $this->category === 'conflict';
    }

    public function isDuplicateReference(): bool
    {
        return $this->category === 'duplicate_reference';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }
}
