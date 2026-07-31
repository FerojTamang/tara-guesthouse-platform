<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Represents a safe verified-stay review workflow failure. */
final class ReviewException extends RuntimeException
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

    public static function bookingNotEligible(?Throwable $previous = null): self
    {
        return new self('This booking is not eligible for a review invitation.', 'eligibility', $previous);
    }

    public static function invitationConflict(?Throwable $previous = null): self
    {
        return new self('A review invitation already exists for this booking.', 'conflict', $previous);
    }

    public static function reviewConflict(?Throwable $previous = null): self
    {
        return new self('A review already exists for this booking.', 'conflict', $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Review workflow operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool { return $this->category === 'validation'; }
    public function isEligibilityFailure(): bool { return $this->category === 'eligibility'; }
    public function isConflict(): bool { return $this->category === 'conflict'; }
    public function isSystemFailure(): bool { return $this->category === 'system'; }
}
