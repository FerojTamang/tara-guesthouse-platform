<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reports one committed booking status transition without Guest information.
 */
final readonly class BookingStatusTransitionResult
{
    public function __construct(
        private int $bookingId,
        private string $bookingReference,
        private string $fromStatus,
        private string $toStatus,
        private DateTimeImmutable $changedAt,
    ) {
        if (
            $bookingId <= 0
            || $bookingReference === ''
            || $bookingReference !== trim($bookingReference)
            || strlen($bookingReference) > 30
            || !Booking::isStatus($fromStatus)
            || !Booking::isStatus($toStatus)
            || $fromStatus === $toStatus
            || $changedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Booking status transition result is invalid.');
        }
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function bookingReference(): string
    {
        return $this->bookingReference;
    }

    public function fromStatus(): string
    {
        return $this->fromStatus;
    }

    public function toStatus(): string
    {
        return $this->toStatus;
    }

    public function changedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }
}
