<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Carries safe public failure categories for the content media library. */
final class ContentMediaException extends RuntimeException
{
    private const VALIDATION = 'validation';
    private const NOT_FOUND = 'not_found';
    private const CONFLICT = 'conflict';
    private const FAILURE = 'failure';

    private function __construct(
        string $message,
        private readonly string $category,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, self::VALIDATION);
    }

    public static function notFound(): self
    {
        return new self('The requested media item was not found.', self::NOT_FOUND);
    }

    public static function conflict(string $message): self
    {
        return new self($message, self::CONFLICT);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('The media operation could not be completed.', self::FAILURE, $previous);
    }

    public function isValidationFailure(): bool
    {
        return $this->category === self::VALIDATION;
    }

    public function isNotFound(): bool
    {
        return $this->category === self::NOT_FOUND;
    }

    public function isConflict(): bool
    {
        return $this->category === self::CONFLICT;
    }
}
