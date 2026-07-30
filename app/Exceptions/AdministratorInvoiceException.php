<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents a safe Administrator invoice read or issue failure.
 */
final class AdministratorInvoiceException extends RuntimeException
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

    public static function notFound(): self
    {
        return new self('The invoice or booking was not found.', 'not_found');
    }

    public static function conflict(): self
    {
        return new self('The booking is not eligible for invoice issue.', 'conflict');
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Administrator invoice operation failed.', 'system', $previous);
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
