<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents one sanitized gallery-image validation or storage failure.
 */
final class GalleryImageException extends RuntimeException
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

    public static function storage(?Throwable $previous = null): self
    {
        return new self('Gallery image storage failed.', 'storage', $previous);
    }

    public static function system(?Throwable $previous = null): self
    {
        return new self('Gallery image operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isStorageFailure(): bool
    {
        return $this->category === 'storage';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }
}
