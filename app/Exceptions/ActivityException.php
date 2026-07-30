<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents one safe activity-catalogue failure.
 */
final class ActivityException extends RuntimeException
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

    public static function notFound(?Throwable $previous = null): self
    {
        return new self('The requested activity was not found.', 'not_found', $previous);
    }

    public static function duplicateSlug(?Throwable $previous = null): self
    {
        return new self('An activity with this slug already exists.', 'conflict', $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Activity catalogue operation failed.', 'system', $previous);
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

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }
}
