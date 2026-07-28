<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Requests\GuestBookingRequest;
use DateTimeImmutable;

/**
 * Defines the already-transactional pending-booking creation boundary.
 */
interface BookingCreator
{
    public function createPending(
        GuestBookingRequest $request,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): Booking;
}
