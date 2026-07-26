<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingRoom;

/**
 * Defines the read operations required by booking workflows and administration.
 */
interface BookingCatalogRepository
{
    public function findById(int $id): ?Booking;

    public function findByReference(string $bookingReference): ?Booking;

    public function findRoomByBookingId(int $bookingId): ?BookingRoom;

    /** @return list<BookingEvent> */
    public function findEventsByBookingId(int $bookingId): array;
}
