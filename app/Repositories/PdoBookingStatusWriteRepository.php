<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Models\BookingStatusSnapshot;
use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Locks, updates, and records one booking state change atomically.
 */
final readonly class PdoBookingStatusWriteRepository implements BookingStatusWriteRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function lockCurrent(int $bookingId): ?BookingStatusSnapshot
    {
        if ($bookingId <= 0 || !$this->connection->inTransaction()) {
            throw new DatabaseException('The booking status lock failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'SELECT id, booking_reference, booking_status '
                . 'FROM bookings '
                . 'WHERE id = :booking_id AND deleted_at IS NULL '
                . 'LIMIT 1 FOR UPDATE',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The booking status record could not be mapped.');
            }

            $id = filter_var(
                $row['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );
            $reference = $row['booking_reference'] ?? null;
            $status = $row['booking_status'] ?? null;

            if (!is_int($id) || !is_string($reference) || !is_string($status)) {
                throw new RuntimeException('The booking status record could not be mapped.');
            }

            return new BookingStatusSnapshot($id, $reference, $status);
        } catch (Throwable) {
            throw new DatabaseException('The booking status lock failed.');
        }
    }

    public function updateStatus(
        BookingStatusSnapshot $current,
        string $targetStatus,
        ?string $reason,
        DateTimeImmutable $changedAt,
    ): void {
        if (
            !$this->connection->inTransaction()
            || !Booking::isStatus($targetStatus)
            || $changedAt->getOffset() !== 0
        ) {
            throw new DatabaseException('The booking status update failed.');
        }

        try {
            [$assignment, $parameters] = match ($targetStatus) {
                Booking::STATUS_CONFIRMED => [
                    'confirmed_at = :state_at',
                    [],
                ],
                Booking::STATUS_REJECTED => [
                    'rejected_at = :state_at, rejection_reason = :reason',
                    ['reason' => $reason],
                ],
                Booking::STATUS_CANCELLED => [
                    'cancelled_at = :state_at, cancellation_reason = :reason',
                    ['reason' => $reason],
                ],
                Booking::STATUS_CHECKED_IN => [
                    'checked_in_at = :state_at',
                    [],
                ],
                Booking::STATUS_CHECKED_OUT => [
                    'checked_out_at = :state_at',
                    [],
                ],
                default => throw new RuntimeException(
                    'The target booking status is not writable.',
                ),
            };
            $timestamp = $changedAt->format('Y-m-d H:i:s');
            $statement = $this->connection->prepare(
                'UPDATE bookings SET '
                . 'booking_status = :target_status, '
                . $assignment
                . ', updated_at = :updated_at '
                . 'WHERE id = :booking_id '
                . 'AND BINARY booking_status = BINARY :current_status '
                . 'AND deleted_at IS NULL',
            );
            $statement->execute([
                'target_status' => $targetStatus,
                'state_at' => $timestamp,
                'updated_at' => $timestamp,
                'booking_id' => $current->id(),
                'current_status' => $current->status(),
                ...$parameters,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The booking status changed concurrently.');
            }
        } catch (Throwable) {
            throw new DatabaseException('The booking status update failed.');
        }
    }

    public function appendEvent(
        int $bookingId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        int $performedBy,
        ?string $reason,
        DateTimeImmutable $createdAt,
        ?string $metadataJson = null,
    ): void {
        if (
            !$this->connection->inTransaction()
            || $bookingId <= 0
            || $performedBy <= 0
            || $eventType === ''
            || $eventType !== trim($eventType)
            || strlen($eventType) > 50
            || !Booking::isStatus($fromStatus)
            || !Booking::isStatus($toStatus)
            || $fromStatus === $toStatus
            || $createdAt->getOffset() !== 0
            || !$this->isValidMetadata($metadataJson)
        ) {
            throw new DatabaseException('The booking event write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO booking_events ('
                . 'booking_id, event_type, from_status, to_status, '
                . 'performed_by, reason, metadata_json, created_at'
                . ') VALUES ('
                . ':booking_id, :event_type, :from_status, :to_status, '
                . ':performed_by, :reason, :metadata_json, :created_at'
                . ')',
            );
            $statement->execute([
                'booking_id' => $bookingId,
                'event_type' => $eventType,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'performed_by' => $performedBy,
                'reason' => $reason,
                'metadata_json' => $metadataJson,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            throw new DatabaseException('The booking event write failed.');
        }
    }

    private function isValidMetadata(?string $metadataJson): bool
    {
        if ($metadataJson === null) {
            return true;
        }

        try {
            json_decode($metadataJson, true, 32, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }
}
