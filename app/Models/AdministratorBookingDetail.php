<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * Aggregates one booking with its immutable one-room snapshot and event history.
 */
final readonly class AdministratorBookingDetail
{
    /**
     * @param list<BookingEvent> $events
     */
    public function __construct(
        private Booking $booking,
        private BookingRoom $room,
        private array $events,
    ) {
        if ($room->bookingId() !== $booking->id()) {
            throw new InvalidArgumentException('Administrator booking detail is invalid.');
        }

        foreach ($events as $event) {
            if (
                !$event instanceof BookingEvent
                || $event->bookingId() !== $booking->id()
            ) {
                throw new InvalidArgumentException('Administrator booking detail is invalid.');
            }
        }
    }

    public function booking(): Booking
    {
        return $this->booking;
    }

    public function room(): BookingRoom
    {
        return $this->room;
    }

    /** @return list<BookingEvent> */
    public function events(): array
    {
        return $this->events;
    }
}
