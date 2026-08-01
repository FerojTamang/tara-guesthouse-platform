<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Represents one safe Administrator notification workflow failure. */
final class NotificationException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
    ) {
        parent::__construct($message);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function notFound(): self
    {
        return new self('The requested notification was not found.', 'not_found');
    }

    public static function failure(): self
    {
        return new self('Notification workflow operation failed.', 'system');
    }

    public function isValidationFailure(): bool { return $this->category === 'validation'; }
    public function isNotFound(): bool { return $this->category === 'not_found'; }
    public function isSystemFailure(): bool { return $this->category === 'system'; }
}
