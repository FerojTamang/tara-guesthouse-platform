<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Exceptions\RoomCatalogException;
use App\Models\Room;
use App\Models\RoomType;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Provides persistence access to physical rooms.
 */
final class RoomRepository implements RoomCatalogRepository
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

    /** @return list<int> */
    public function findAmenityIds(int $roomId): array
    {
        if ($roomId <= 0) {
            return [];
        }

        try {
            $statement = $this->connection->prepare(
                'SELECT amenity_id FROM room_amenities WHERE room_id = :room_id ORDER BY amenity_id ASC',
            );
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The room amenity assignments could not be mapped.');
            }

            $amenityIds = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The room amenity assignments could not be mapped.');
                }

                $amenityIds[] = $this->positiveInteger($row, 'amenity_id');
            }

            return $amenityIds;
        } catch (Throwable) {
            throw new DatabaseException('The room amenity lookup failed.');
        }
    }

    public function create(
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
    ): Room {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO rooms ('
                . 'room_type_id, room_number, name, slug, description, price_per_night, capacity_adults, '
                . 'capacity_children, operational_status, is_published'
                . ') VALUES ('
                . ':room_type_id, :room_number, :name, :slug, :description, :price_per_night, '
                . ':capacity_adults, :capacity_children, :operational_status, :is_published'
                . ')',
            );
            $statement->execute([
                'room_type_id' => $roomTypeId,
                'room_number' => $roomNumber,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'price_per_night' => $pricePerNight,
                'capacity_adults' => $capacityAdults,
                'capacity_children' => $capacityChildren,
                'operational_status' => $operationalStatus,
                'is_published' => $published ? 1 : 0,
            ]);

            $room = $this->findById($this->generatedIdentifier());

            if (!$room instanceof Room) {
                throw new RuntimeException('The created room could not be loaded.');
            }

            return $room;
        } catch (PDOException $exception) {
            $this->throwWriteFailure($exception);
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The room write failed.');
        }
    }

    public function update(
        int $id,
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
    ): ?Room {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE rooms SET '
                . 'room_type_id = :room_type_id, room_number = :room_number, name = :name, slug = :slug, '
                . 'description = :description, price_per_night = :price_per_night, '
                . 'capacity_adults = :capacity_adults, capacity_children = :capacity_children, '
                . 'operational_status = :operational_status, is_published = :is_published '
                . 'WHERE id = :id AND deleted_at IS NULL',
            );
            $statement->execute([
                'id' => $id,
                'room_type_id' => $roomTypeId,
                'room_number' => $roomNumber,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'price_per_night' => $pricePerNight,
                'capacity_adults' => $capacityAdults,
                'capacity_children' => $capacityChildren,
                'operational_status' => $operationalStatus,
                'is_published' => $published ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (PDOException $exception) {
            $this->throwWriteFailure($exception);
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The room write failed.');
        }
    }

    /** @param list<int> $amenityIds */
    public function replaceAmenities(int $roomId, array $amenityIds): void
    {
        if ($roomId <= 0 || $amenityIds !== array_values(array_unique($amenityIds))) {
            throw new DatabaseException('The room amenity update failed.');
        }

        foreach ($amenityIds as $amenityId) {
            if (!is_int($amenityId) || $amenityId <= 0) {
                throw new DatabaseException('The room amenity update failed.');
            }
        }

        try {
            $delete = $this->connection->prepare('DELETE FROM room_amenities WHERE room_id = :room_id');
            $delete->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $delete->execute();

            if ($amenityIds === []) {
                return;
            }

            $insert = $this->connection->prepare(
                'INSERT INTO room_amenities (room_id, amenity_id) VALUES (:room_id, :amenity_id)',
            );

            foreach ($amenityIds as $amenityId) {
                $insert->bindValue('room_id', $roomId, PDO::PARAM_INT);
                $insert->bindValue('amenity_id', $amenityId, PDO::PARAM_INT);
                $insert->execute();
            }
        } catch (Throwable) {
            throw new DatabaseException('The room amenity update failed.');
        }
    }

    public function softDelete(int $id): ?Room
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE rooms SET deleted_at = UTC_TIMESTAMP() WHERE id = :id AND deleted_at IS NULL',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();

            if ($statement->rowCount() === 0) {
                return null;
            }

            $room = $this->findByIdIncludingDeleted($id);

            if (!$room instanceof Room || !$room->isDeleted()) {
                throw new RuntimeException('The deleted room could not be loaded.');
            }

            return $room;
        } catch (Throwable) {
            throw new DatabaseException('The room deletion failed.');
        }
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

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated room identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated room identifier is invalid.');
        }

        return $integer;
    }

    private function throwWriteFailure(PDOException $exception): never
    {
        if ((string) $exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
            $details = (string) ($exception->errorInfo[2] ?? '');

            if (str_contains($details, 'uq_rooms_room_number')) {
                throw RoomCatalogException::duplicateRoomNumber($exception);
            }

            if (str_contains($details, 'uq_rooms_slug')) {
                throw RoomCatalogException::duplicateRoomSlug($exception);
            }
        }

        throw new DatabaseException('The room write failed.');
    }
}
