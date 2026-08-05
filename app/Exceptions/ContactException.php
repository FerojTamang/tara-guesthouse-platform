<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Represents one safe contact-workflow failure. */
final class ContactException extends RuntimeException
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
        return new self('The requested contact message was not found.', 'not_found', $previous);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 'conflict');
    }

    public static function delivery(): self
    {
        return new self(
            'The email reply could not be delivered. Check the mail configuration and try again.',
            'delivery',
        );
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Contact workflow operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool { return $this->category === 'validation'; }
    public function isNotFound(): bool { return $this->category === 'not_found'; }
    public function isConflict(): bool { return $this->category === 'conflict'; }
    public function isDeliveryFailure(): bool { return $this->category === 'delivery'; }
    public function isSystemFailure(): bool { return $this->category === 'system'; }
}
