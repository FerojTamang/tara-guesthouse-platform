<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Represents a safe CMS/settings failure. */
final class CmsException extends RuntimeException
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

    public static function settingNotFound(?Throwable $previous = null): self
    {
        return new self('The requested setting was not found.', 'not_found', $previous);
    }

    public static function pageNotFound(?Throwable $previous = null): self
    {
        return new self('The requested page was not found.', 'not_found', $previous);
    }

    public static function navigationItemNotFound(?Throwable $previous = null): self
    {
        return new self('The requested navigation item was not found.', 'not_found', $previous);
    }

    public static function navigationConflict(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 'conflict', $previous);
    }

    public static function duplicatePageSlug(?Throwable $previous = null): self
    {
        return new self('A page with this slug already exists.', 'conflict', $previous);
    }

    public static function transitionConflict(?Throwable $previous = null): self
    {
        return new self('The page status changed before the transition completed.', 'conflict', $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('CMS operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool { return $this->category === 'validation'; }
    public function isNotFound(): bool { return $this->category === 'not_found'; }
    public function isConflict(): bool { return $this->category === 'conflict'; }
    public function isSystemFailure(): bool { return $this->category === 'system'; }
}
