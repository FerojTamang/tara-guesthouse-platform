<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents safe payment-proof validation, storage, or cleanup failures.
 */
final class PaymentProofException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
        ?Throwable $previous = null,
        private readonly ?Throwable $cleanupFailure = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidInput(string $message): self
    {
        return new self($message, 'validation');
    }

    public static function storage(?Throwable $previous = null): self
    {
        return new self('Payment proof storage failed.', 'storage', $previous);
    }

    public static function cleanup(Throwable $operationFailure, Throwable $cleanupFailure): self
    {
        return new self(
            'Payment proof cleanup failed.',
            'cleanup',
            $operationFailure,
            $cleanupFailure,
        );
    }

    public static function system(?Throwable $previous = null): self
    {
        return new self('Payment proof operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isStorageFailure(): bool
    {
        return $this->category === 'storage';
    }

    public function isCleanupFailure(): bool
    {
        return $this->category === 'cleanup';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }

    public function cleanupCause(): ?Throwable
    {
        return $this->cleanupFailure;
    }
}
