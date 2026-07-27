<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final readonly class BookingSubmissionResult
{
    public function __construct(
        private Booking $booking,
        private string $bookingAccessToken,
    ) {
        if (
            $booking->bookingStatus() !== Booking::STATUS_PENDING
            || $booking->confirmationDeadlineAt() === null
            || preg_match('/^v1\.[A-Za-z0-9_-]{43}$/D', $bookingAccessToken) !== 1
        ) {
            throw new InvalidArgumentException('Booking submission result is invalid.');
        }
    }

    public function booking(): Booking
    {
        return $this->booking;
    }

    public function bookingAccessToken(): string
    {
        return $this->bookingAccessToken;
    }
}
