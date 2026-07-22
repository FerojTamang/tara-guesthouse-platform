<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Exceptions\RoomCatalogException;
use App\Models\RoomType;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Provides persistence access to the room-type catalogue.
 */
final class RoomTypeRepository implements RoomTypeCatalogRepository
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

    public function create(
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): RoomType {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO room_types ('
                . 'name, slug, description, base_price, capacity_adults, capacity_children, is_active'
                . ') VALUES ('
                . ':name, :slug, :description, :base_price, :capacity_adults, :capacity_children, :is_active'
                . ')',
            );
            $statement->execute([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'base_price' => $basePrice,
                'capacity_adults' => $capacityAdults,
                'capacity_children' => $capacityChildren,
                'is_active' => $active ? 1 : 0,
            ]);

            $roomType = $this->findById($this->generatedIdentifier());

            if (!$roomType instanceof RoomType) {
                throw new RuntimeException('The created room type could not be loaded.');
            }

            return $roomType;
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw RoomCatalogException::duplicateRoomTypeSlug($exception);
            }

            throw new DatabaseException('The room type write failed.');
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The room type write failed.');
        }
    }

    public function update(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): ?RoomType {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE room_types SET '
                . 'name = :name, slug = :slug, description = :description, base_price = :base_price, '
                . 'capacity_adults = :capacity_adults, capacity_children = :capacity_children, '
                . 'is_active = :is_active '
                . 'WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'base_price' => $basePrice,
                'capacity_adults' => $capacityAdults,
                'capacity_children' => $capacityChildren,
                'is_active' => $active ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw RoomCatalogException::duplicateRoomTypeSlug($exception);
            }

            throw new DatabaseException('The room type write failed.');
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The room type write failed.');
        }
    }

    public function setActive(int $id, bool $active): ?RoomType
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE room_types SET is_active = :is_active WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'is_active' => $active ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The room type state update failed.');
        }
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

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated room type identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated room type identifier is invalid.');
        }

        return $integer;
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
