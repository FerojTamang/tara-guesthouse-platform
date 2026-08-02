<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\StayPeriod;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Executes the canonical Version 1 room-availability and overlap queries.
 */
final class PdoAvailabilityRepository implements AvailabilityRepository
{
    use MapsRepositoryRows;

    private const BLOCKING_BOOKING_FILTER = <<<'SQL'
        b.deleted_at IS NULL
        AND (
            BINARY b.booking_status = BINARY 'confirmed'
            OR BINARY b.booking_status = BINARY 'checked_in'
            OR (
                BINARY b.booking_status = BINARY 'pending'
                AND b.confirmation_deadline_at > :as_of
            )
        )
        AND b.check_in < :requested_check_out
        AND b.check_out > :requested_check_in
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return list<int>
     */
    public function findAvailableRoomIds(
        StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array {
        if ($adults < 1 || $children < 0) {
            return [];
        }

        $this->assertUtc($asOf, 'The availability lookup failed.');

        try {
            $statement = $this->connection->prepare(
                'SELECT r.id '
                . 'FROM rooms r '
                . 'WHERE r.deleted_at IS NULL '
                . "AND r.operational_status = 'active' "
                . 'AND r.is_published = 1 '
                . 'AND r.capacity_adults >= :adults '
                . 'AND r.capacity_children >= :children '
                . 'AND NOT EXISTS ('
                . 'SELECT 1 '
                . 'FROM booking_rooms br '
                . 'INNER JOIN bookings b ON b.id = br.booking_id '
                . 'WHERE br.room_id = r.id AND '
                . self::BLOCKING_BOOKING_FILTER
                . ') '
                . 'ORDER BY r.room_number ASC, r.id ASC',
            );
            $statement->bindValue('adults', $adults, PDO::PARAM_INT);
            $statement->bindValue('children', $children, PDO::PARAM_INT);
            $this->bindPeriod($statement, $stayPeriod, $asOf);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The available room identifiers could not be mapped.');
            }

            $roomIds = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The available room identifiers could not be mapped.');
                }

                $roomIds[] = $this->positiveInteger($row, 'id');
            }

            return $roomIds;
        } catch (Throwable) {
            throw new DatabaseException('The availability lookup failed.');
        }
    }

    public function hasBlockingOverlap(
        int $roomId,
        StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool {
        if ($roomId <= 0) {
            return false;
        }

        $this->assertUtc($asOf, 'The booking overlap lookup failed.');

        try {
            $statement = $this->connection->prepare(
                'SELECT 1 '
                . 'FROM booking_rooms br '
                . 'INNER JOIN bookings b ON b.id = br.booking_id '
                . 'WHERE br.room_id = :room_id AND '
                . self::BLOCKING_BOOKING_FILTER
                . ' LIMIT 1',
            );
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $this->bindPeriod($statement, $stayPeriod, $asOf);
            $statement->execute();

            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            throw new DatabaseException('The booking overlap lookup failed.');
        }
    }

    public function findBlockingStayPeriods(
        int $roomId,
        StayPeriod $window,
        DateTimeImmutable $asOf,
    ): array {
        if ($roomId <= 0) {
            return [];
        }

        $this->assertUtc($asOf, 'The availability calendar lookup failed.');

        try {
            $statement = $this->connection->prepare(
                'SELECT b.check_in, b.check_out '
                . 'FROM booking_rooms br '
                . 'INNER JOIN bookings b ON b.id = br.booking_id '
                . 'WHERE br.room_id = :room_id AND '
                . self::BLOCKING_BOOKING_FILTER
                . ' ORDER BY b.check_in ASC, b.check_out ASC, b.id ASC',
            );
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $this->bindPeriod($statement, $window, $asOf);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The availability calendar ranges could not be mapped.');
            }

            $periods = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The availability calendar ranges could not be mapped.');
                }

                $checkIn = $row['check_in'] ?? null;
                $checkOut = $row['check_out'] ?? null;

                if (!is_string($checkIn) || !is_string($checkOut)) {
                    throw new RuntimeException('The availability calendar ranges could not be mapped.');
                }

                $periods[] = new StayPeriod(
                    new DateTimeImmutable($checkIn . ' 00:00:00 UTC'),
                    new DateTimeImmutable($checkOut . ' 00:00:00 UTC'),
                );
            }

            return $periods;
        } catch (Throwable) {
            throw new DatabaseException('The availability calendar lookup failed.');
        }
    }

    private function bindPeriod(
        \PDOStatement $statement,
        StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): void {
        $statement->bindValue(
            'requested_check_in',
            $stayPeriod->checkIn()->format('Y-m-d'),
            PDO::PARAM_STR,
        );
        $statement->bindValue(
            'requested_check_out',
            $stayPeriod->checkOut()->format('Y-m-d'),
            PDO::PARAM_STR,
        );
        $statement->bindValue('as_of', $asOf->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    }

    private function assertUtc(DateTimeImmutable $dateTime, string $message): void
    {
        if ($dateTime->getOffset() !== 0) {
            throw new DatabaseException($message);
        }
    }
}
