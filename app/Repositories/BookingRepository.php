<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingRoom;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides strict read access to booking records, the one-room snapshot, and event history.
 */
final class BookingRepository implements BookingCatalogRepository
{
    use MapsRepositoryRows;

    private const BOOKING_COLUMNS = <<<'SQL'
        SELECT
            id,
            booking_reference,
            user_id,
            guest_full_name,
            guest_email,
            guest_phone,
            check_in,
            check_out,
            adults,
            children,
            currency,
            room_amount,
            total_amount,
            booking_status,
            special_request,
            terms_accepted_at,
            policy_version,
            confirmation_deadline_at,
            confirmed_at,
            checked_in_at,
            checked_out_at,
            cancelled_at,
            cancellation_reason,
            rejected_at,
            rejection_reason,
            created_at,
            updated_at,
            deleted_at
        FROM bookings
        SQL;

    private const BOOKING_ROOM_COLUMNS = <<<'SQL'
        SELECT
            id,
            booking_id,
            room_id,
            price_per_night,
            nights,
            subtotal,
            created_at
        FROM booking_rooms
        SQL;

    private const BOOKING_EVENT_COLUMNS = <<<'SQL'
        SELECT
            id,
            booking_id,
            event_type,
            from_status,
            to_status,
            performed_by,
            reason,
            metadata_json,
            created_at
        FROM booking_events
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<Booking>
     */
    public function findAll(?string $status = null): array
    {
        if ($status !== null && !Booking::isStatus($status)) {
            return [];
        }

        try {
            $sql = self::BOOKING_COLUMNS . ' WHERE deleted_at IS NULL';

            if ($status !== null) {
                $sql .= ' AND booking_status = :booking_status';
            }

            $sql .= ' ORDER BY created_at DESC, id DESC';
            $statement = $this->connection->prepare($sql);

            if ($status !== null) {
                $statement->bindValue('booking_status', $status, PDO::PARAM_STR);
            }

            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The booking records could not be mapped.');
            }

            $bookings = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The booking records could not be mapped.');
                }

                $bookings[] = $this->mapBooking($row);
            }

            return $bookings;
        } catch (Throwable) {
            throw new DatabaseException('The booking listing failed.');
        }
    }

    public function findById(int $id): ?Booking
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookupBooking(
            self::BOOKING_COLUMNS . ' WHERE id = :lookup AND deleted_at IS NULL LIMIT 1',
            $id,
            PDO::PARAM_INT,
        );
    }

    public function findByReference(string $bookingReference): ?Booking
    {
        if ($bookingReference === '') {
            return null;
        }

        return $this->lookupBooking(
            self::BOOKING_COLUMNS . ' WHERE booking_reference = :lookup AND deleted_at IS NULL LIMIT 1',
            $bookingReference,
            PDO::PARAM_STR,
        );
    }

    public function findRoomByBookingId(int $bookingId): ?BookingRoom
    {
        if ($bookingId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::BOOKING_ROOM_COLUMNS . ' WHERE booking_id = :booking_id LIMIT 1',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The booking room record could not be mapped.');
            }

            return $this->mapBookingRoom($row);
        } catch (Throwable) {
            throw new DatabaseException('The booking room lookup failed.');
        }
    }

    /**
     * @return list<BookingEvent>
     */
    public function findEventsByBookingId(int $bookingId): array
    {
        if ($bookingId <= 0) {
            return [];
        }

        try {
            $statement = $this->connection->prepare(
                self::BOOKING_EVENT_COLUMNS
                . ' WHERE booking_id = :booking_id ORDER BY created_at ASC, id ASC',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The booking event records could not be mapped.');
            }

            $events = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The booking event records could not be mapped.');
                }

                $events[] = $this->mapBookingEvent($row);
            }

            return $events;
        } catch (Throwable) {
            throw new DatabaseException('The booking event listing failed.');
        }
    }

    private function lookupBooking(string $sql, int|string $value, int $type): ?Booking
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, $type);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The booking record could not be mapped.');
            }

            return $this->mapBooking($row);
        } catch (Throwable) {
            throw new DatabaseException('The booking lookup failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapBooking(array $row): Booking
    {
        return new Booking(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'booking_reference'),
            $this->nullablePositiveInteger($row, 'user_id'),
            $this->string($row, 'guest_full_name'),
            $this->string($row, 'guest_email'),
            $this->string($row, 'guest_phone'),
            $this->date($row, 'check_in'),
            $this->date($row, 'check_out'),
            $this->positiveInteger($row, 'adults'),
            $this->nonNegativeInteger($row, 'children'),
            $this->string($row, 'currency'),
            $this->string($row, 'room_amount'),
            $this->string($row, 'total_amount'),
            $this->string($row, 'booking_status'),
            $this->nullableString($row, 'special_request'),
            $this->dateTime($row, 'terms_accepted_at'),
            $this->nullableString($row, 'policy_version'),
            $this->dateTime($row, 'confirmation_deadline_at'),
            $this->nullableDateTime($row, 'confirmed_at'),
            $this->nullableDateTime($row, 'checked_in_at'),
            $this->nullableDateTime($row, 'checked_out_at'),
            $this->nullableDateTime($row, 'cancelled_at'),
            $this->nullableString($row, 'cancellation_reason'),
            $this->nullableDateTime($row, 'rejected_at'),
            $this->nullableString($row, 'rejection_reason'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->nullableDateTime($row, 'deleted_at'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapBookingRoom(array $row): BookingRoom
    {
        return new BookingRoom(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'booking_id'),
            $this->positiveInteger($row, 'room_id'),
            $this->string($row, 'price_per_night'),
            $this->positiveInteger($row, 'nights'),
            $this->string($row, 'subtotal'),
            $this->dateTime($row, 'created_at'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapBookingEvent(array $row): BookingEvent
    {
        return new BookingEvent(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'booking_id'),
            $this->string($row, 'event_type'),
            $this->nullableString($row, 'from_status'),
            $this->nullableString($row, 'to_status'),
            $this->nullablePositiveInteger($row, 'performed_by'),
            $this->nullableString($row, 'reason'),
            $this->nullableString($row, 'metadata_json'),
            $this->dateTime($row, 'created_at'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->positiveInteger($row, $key);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function date(array $row, string $key): DateTimeImmutable
    {
        $value = $this->string($row, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $date;
    }
}
