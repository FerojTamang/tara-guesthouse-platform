<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\RoomType;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides read-only persistence access to the room-type catalogue.
 */
final class RoomTypeRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            id,
            name,
            slug,
            description,
            base_price,
            capacity_adults,
            capacity_children,
            is_active,
            created_at,
            updated_at
        FROM room_types
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?RoomType
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(self::SELECT_COLUMNS . ' WHERE id = :lookup LIMIT 1', $id, PDO::PARAM_INT);
    }

    /**
     * Finds a room type using the configured database collation without application normalization.
     */
    public function findBySlug(string $slug): ?RoomType
    {
        if ($slug === '') {
            return null;
        }

        return $this->lookup(self::SELECT_COLUMNS . ' WHERE slug = :lookup LIMIT 1', $slug, PDO::PARAM_STR);
    }

    /**
     * @return list<RoomType>
     */
    public function findAll(): array
    {
        return $this->listing(self::SELECT_COLUMNS . ' ORDER BY name ASC, id ASC');
    }

    /**
     * @return list<RoomType>
     */
    public function findAllActive(): array
    {
        return $this->listing(self::SELECT_COLUMNS . ' WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    private function lookup(string $sql, int|string $value, int $type): ?RoomType
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
                throw new RuntimeException('The room type record could not be mapped.');
            }

            return $this->mapRoomType($row);
        } catch (Throwable) {
            throw new DatabaseException('The room type lookup failed.');
        }
    }

    /**
     * @return list<RoomType>
     */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The room type records could not be mapped.');
            }

            $roomTypes = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The room type records could not be mapped.');
                }

                $roomTypes[] = $this->mapRoomType($row);
            }

            return $roomTypes;
        } catch (Throwable) {
            throw new DatabaseException('The room type listing failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRoomType(array $row): RoomType
    {
        return new RoomType(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'name'),
            $this->string($row, 'slug'),
            $this->nullableString($row, 'description'),
            $this->string($row, 'base_price'),
            $this->positiveInteger($row, 'capacity_adults'),
            $this->nonNegativeInteger($row, 'capacity_children'),
            $this->boolean($row, 'is_active'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

}
