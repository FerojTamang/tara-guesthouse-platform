<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents one safe gallery catalogue failure.
 */
final class GalleryException extends RuntimeException
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

    public static function categoryNotFound(?Throwable $previous = null): self
    {
        return new self('The requested gallery category was not found.', 'not_found', $previous);
    }

    public static function itemNotFound(?Throwable $previous = null): self
    {
        return new self('The requested gallery item was not found.', 'not_found', $previous);
    }

    public static function duplicateCategoryName(?Throwable $previous = null): self
    {
        return new self('A gallery category with this name already exists.', 'conflict', $previous);
    }

    public static function duplicateCategorySlug(?Throwable $previous = null): self
    {
        return new self('A gallery category with this slug already exists.', 'conflict', $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Gallery catalogue operation failed.', 'system', $previous);
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
