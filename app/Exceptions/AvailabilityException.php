<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents a safe availability-validation or availability-workflow failure.
 */
final class AvailabilityException extends RuntimeException
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

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Availability operation failed.', 'system', $previous);
    }

    public static function notFound(): self
    {
        return new self('The requested room was not found.', 'not_found');
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }

    public function isNotFound(): bool
    {
        return $this->category === 'not_found';
    }
}
