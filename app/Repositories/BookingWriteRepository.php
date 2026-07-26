<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Booking;
use App\Models\BookingQuote;
use DateTimeImmutable;

/**
 * Defines the atomic booking-record writes executed inside a caller-owned transaction.
 */
interface BookingWriteRepository
{
    public function createPending(
        BookingQuote $quote,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): Booking;
}
