<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\AdministratorBookingDetail;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Repositories\BookingCatalogRepository;
use Throwable;

/**
 * Provides read-only Administrator booking-list and detail workflows.
 */
final readonly class AdministratorBookingService
{
    public function __construct(private BookingCatalogRepository $bookings)
    {
    }

    /**
     * @return list<Booking>
     */
    public function list(?string $status = null): array
    {
        try {
            if ($status !== null && !Booking::isStatus($status)) {
                throw BookingException::validation(
                    'Booking status filter is invalid.',
                );
            }

            return $this->bookings->findAll($status);
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    public function detail(int $bookingId): ?AdministratorBookingDetail
    {
        if ($bookingId <= 0) {
            return null;
        }

        try {
            $booking = $this->bookings->findById($bookingId);

            if (!$booking instanceof Booking) {
                return null;
            }

            $room = $this->bookings->findRoomByBookingId($bookingId);

            if (!$room instanceof BookingRoom) {
                throw BookingException::failure();
            }

            return new AdministratorBookingDetail(
                $booking,
                $room,
                $this->bookings->findEventsByBookingId($bookingId),
            );
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
