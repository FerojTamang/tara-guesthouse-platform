<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\BookingException;
use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Models\BookingQuote;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Persists one pending booking, its single room snapshot, and its creation event.
 *
 * Transaction ownership and room locking remain service responsibilities.
 */
final readonly class PdoBookingWriteRepository implements BookingWriteRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function createPending(
        BookingQuote $quote,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): Booking {
        $createdAt = $quote->checkedAt();

        if (
            !$this->connection->inTransaction()
            || $bookingReference === ''
            || $bookingReference !== trim($bookingReference)
            || strlen($bookingReference) > 30
            || $createdAt->getOffset() !== 0
            || $confirmationDeadlineAt->getOffset() !== 0
            || $confirmationDeadlineAt <= $createdAt
        ) {
            throw new DatabaseException('The booking write failed.');
        }

        try {
            $bookingId = $this->insertBooking(
                $quote,
                $bookingReference,
                $confirmationDeadlineAt,
            );
            $this->insertBookingRoom($bookingId, $quote);
            $this->insertCreationEvent($bookingId, $createdAt);

            $booking = (new BookingRepository($this->connection))->findById($bookingId);

            if (!$booking instanceof Booking) {
                throw new RuntimeException('The created booking could not be loaded.');
            }

            return $booking;
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The booking write failed.');
        }
    }

    private function insertBooking(
        BookingQuote $quote,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): int {
        $request = $quote->request();
        $createdAt = $quote->checkedAt()->format('Y-m-d H:i:s');

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO bookings ('
                . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
                . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
                . 'booking_status, special_request, terms_accepted_at, policy_version, '
                . 'confirmation_deadline_at, created_at, updated_at'
                . ') VALUES ('
                . ':booking_reference, NULL, :guest_full_name, :guest_email, :guest_phone, '
                . ':check_in, :check_out, :adults, :children, :currency, :room_amount, :total_amount, '
                . ':booking_status, :special_request, :terms_accepted_at, :policy_version, '
                . ':confirmation_deadline_at, :created_at, :updated_at'
                . ')',
            );
            $statement->execute([
                'booking_reference' => $bookingReference,
                'guest_full_name' => $request->guestFullName(),
                'guest_email' => $request->guestEmail(),
                'guest_phone' => $request->guestPhone(),
                'check_in' => $request->stayPeriod()->checkIn()->format('Y-m-d'),
                'check_out' => $request->stayPeriod()->checkOut()->format('Y-m-d'),
                'adults' => $request->adults(),
                'children' => $request->children(),
                'currency' => $quote->currency(),
                'room_amount' => $quote->roomAmount(),
                'total_amount' => $quote->totalAmount(),
                'booking_status' => Booking::STATUS_PENDING,
                'special_request' => $request->specialRequest(),
                'terms_accepted_at' => $createdAt,
                'policy_version' => $request->policyVersion(),
                'confirmation_deadline_at' => $confirmationDeadlineAt->format('Y-m-d H:i:s'),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            return $this->generatedIdentifier();
        } catch (PDOException $exception) {
            if (
                (string) $exception->getCode() === '23000'
                && (int) ($exception->errorInfo[1] ?? 0) === 1062
            ) {
                throw BookingException::duplicateReference($exception);
            }

            throw new DatabaseException('The booking write failed.');
        }
    }

    private function insertBookingRoom(int $bookingId, BookingQuote $quote): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO booking_rooms ('
            . 'booking_id, room_id, price_per_night, nights, subtotal, created_at'
            . ') VALUES ('
            . ':booking_id, :room_id, :price_per_night, :nights, :subtotal, :created_at'
            . ')',
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'room_id' => $quote->room()->id(),
            'price_per_night' => $quote->pricePerNight(),
            'nights' => $quote->nights(),
            'subtotal' => $quote->roomAmount(),
            'created_at' => $quote->checkedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    private function insertCreationEvent(int $bookingId, DateTimeImmutable $createdAt): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO booking_events ('
            . 'booking_id, event_type, from_status, to_status, '
            . 'performed_by, reason, metadata_json, created_at'
            . ') VALUES ('
            . ':booking_id, :event_type, NULL, :to_status, '
            . 'NULL, NULL, NULL, :created_at'
            . ')',
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'event_type' => 'booking_created',
            'to_status' => Booking::STATUS_PENDING,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();
        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated booking identifier is invalid.');
        }

        return $integer;
    }
}
