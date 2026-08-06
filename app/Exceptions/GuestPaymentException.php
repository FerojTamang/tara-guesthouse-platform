<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents a safe Guest payment validation, access, or workflow failure.
 */
final class GuestPaymentException extends RuntimeException
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

    public static function accessDenied(): self
    {
        return new self('Payment access is invalid.', 'access');
    }

    public static function bookingConflict(): self
    {
        return new self('The booking cannot accept a payment.', 'conflict');
    }

    public static function paymentMethodUnavailable(): self
    {
        return new self('The selected payment method is not available.', 'conflict');
    }

    public static function paymentAlreadySubmitted(): self
    {
        return new self(
            'A payment is already under review for this booking.',
            'conflict',
        );
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Payment operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool
    {
        return $this->category === 'validation';
    }

    public function isAccessDenied(): bool
    {
        return $this->category === 'access';
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
