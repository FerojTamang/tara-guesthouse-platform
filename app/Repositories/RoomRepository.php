<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Room;
use App\Models\RoomType;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides read-only persistence access to physical rooms.
 */
final class RoomRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            r.id AS room_id,
            r.room_number,
            r.name AS room_name,
            r.slug AS room_slug,
            r.description AS room_description,
            r.price_per_night,
            r.capacity_adults AS room_capacity_adults,
            r.capacity_children AS room_capacity_children,
            r.operational_status,
            r.is_published,
            r.created_at AS room_created_at,
            r.updated_at AS room_updated_at,
            r.deleted_at,
            rt.id AS room_type_id,
            rt.name AS room_type_name,
            rt.slug AS room_type_slug,
            rt.description AS room_type_description,
            rt.base_price,
            rt.capacity_adults AS room_type_capacity_adults,
            rt.capacity_children AS room_type_capacity_children,
            rt.is_active AS room_type_is_active,
            rt.created_at AS room_type_created_at,
            rt.updated_at AS room_type_updated_at
        FROM rooms r
        INNER JOIN room_types rt ON rt.id = r.room_type_id
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * Finds one current, non-deleted room by its numeric identifier.
     */
    public function findById(int $id): ?Room
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE r.id = :lookup AND r.deleted_at IS NULL LIMIT 1',
            $id,
            PDO::PARAM_INT,
        );
    }

    /**
     * Finds one room by identifier even when it has been soft deleted.
     */
    public function findByIdIncludingDeleted(int $id): ?Room
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(self::SELECT_COLUMNS . ' WHERE r.id = :lookup LIMIT 1', $id, PDO::PARAM_INT);
    }

    /**
     * Finds a current room using the configured database collation without application normalization.
     */
    public function findBySlug(string $slug): ?Room
    {
        if ($slug === '') {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE r.slug = :lookup AND r.deleted_at IS NULL LIMIT 1',
            $slug,
            PDO::PARAM_STR,
        );
    }

    /**
     * @return list<Room>
     */
    public function findAll(): array
    {
        return $this->listing(
            self::SELECT_COLUMNS . ' WHERE r.deleted_at IS NULL ORDER BY r.room_number ASC, r.id ASC',
        );
    }

    /**
     * Returns only current rooms satisfying the approved public-visibility flags.
     *
     * Booking overlap and requested occupancy remain service/query concerns for a later ticket.
     *
     * @return list<Room>
     */
    public function findAllPublic(): array
    {
        return $this->listing(
            self::SELECT_COLUMNS
                . " WHERE r.deleted_at IS NULL AND BINARY r.operational_status = BINARY 'active'"
                . ' AND r.is_published = 1 ORDER BY r.room_number ASC, r.id ASC',
        );
    }

    private function lookup(string $sql, int|string $value, int $type): ?Room
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
                throw new RuntimeException('The room record could not be mapped.');
            }

            return $this->mapRoom($row);
        } catch (Throwable) {
            throw new DatabaseException('The room lookup failed.');
        }
    }

    /**
     * @return list<Room>
     */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The room records could not be mapped.');
            }

            $rooms = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The room records could not be mapped.');
                }

                $rooms[] = $this->mapRoom($row);
            }

            return $rooms;
        } catch (Throwable) {
            throw new DatabaseException('The room listing failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRoom(array $row): Room
    {
        $roomType = new RoomType(
            $this->positiveInteger($row, 'room_type_id'),
            $this->string($row, 'room_type_name'),
            $this->string($row, 'room_type_slug'),
            $this->nullableString($row, 'room_type_description'),
            $this->string($row, 'base_price'),
            $this->positiveInteger($row, 'room_type_capacity_adults'),
            $this->nonNegativeInteger($row, 'room_type_capacity_children'),
            $this->boolean($row, 'room_type_is_active'),
            $this->dateTime($row, 'room_type_created_at'),
            $this->dateTime($row, 'room_type_updated_at'),
        );

        return new Room(
            $this->positiveInteger($row, 'room_id'),
            $roomType,
            $this->string($row, 'room_number'),
            $this->string($row, 'room_name'),
            $this->string($row, 'room_slug'),
            $this->nullableString($row, 'room_description'),
            $this->string($row, 'price_per_night'),
            $this->positiveInteger($row, 'room_capacity_adults'),
            $this->nonNegativeInteger($row, 'room_capacity_children'),
            $this->string($row, 'operational_status'),
            $this->boolean($row, 'is_published'),
            $this->dateTime($row, 'room_created_at'),
            $this->dateTime($row, 'room_updated_at'),
            $this->nullableDateTime($row, 'deleted_at'),
        );
    }
}
