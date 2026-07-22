<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents one safe Administrator-authentication failure.
 */
final class AdministratorAuthenticationException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
        private readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function invalidCredentials(): self
    {
        return new self('The supplied credentials are invalid.', 'credentials');
    }

    public static function rateLimited(int $retryAfterSeconds): self
    {
        return new self(
            'Too many login attempts. Please try again later.',
            'rate_limit',
            max(1, $retryAfterSeconds),
        );
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Administrator authentication failed.', 'system', null, $previous);
    }

    public function category(): string
    {
        return $this->category;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isCredentialFailure(): bool
    {
        return $this->category === 'credentials';
    }

    public function isRateLimited(): bool
    {
        return $this->category === 'rate_limit';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }
}
