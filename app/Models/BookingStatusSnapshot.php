<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * Represents the minimum locked booking state required by a transition.
 */
final readonly class BookingStatusSnapshot
{
    public function __construct(
        private int $id,
        private string $bookingReference,
        private string $status,
    ) {
        if (
            $id <= 0
            || $bookingReference === ''
            || $bookingReference !== trim($bookingReference)
            || strlen($bookingReference) > 30
            || !Booking::isStatus($status)
        ) {
            throw new InvalidArgumentException('Booking status snapshot is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function bookingReference(): string
    {
        return $this->bookingReference;
    }

    public function status(): string
    {
        return $this->status;
    }
}
