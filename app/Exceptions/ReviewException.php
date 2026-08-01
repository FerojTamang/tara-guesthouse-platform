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

    public static function reviewNotFound(): self
    {
        return new self('The requested review was not found.', 'not_found');
    }

    public static function moderationConflict(): self
    {
        return new self('This review can no longer be moderated.', 'conflict');
    }

    public static function invitationUnavailable(?Throwable $previous = null): self
    {
        return new self('This review invitation is unavailable.', 'unavailable', $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Review workflow operation failed.', 'system', $previous);
    }

    public function isValidationFailure(): bool { return $this->category === 'validation'; }
    public function isEligibilityFailure(): bool { return $this->category === 'eligibility'; }
    public function isConflict(): bool { return $this->category === 'conflict'; }
    public function isUnavailable(): bool { return $this->category === 'unavailable'; }
    public function isNotFound(): bool { return $this->category === 'not_found'; }
    public function isSystemFailure(): bool { return $this->category === 'system'; }
}
