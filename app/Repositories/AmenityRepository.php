<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Provides persistence access to the amenity catalogue.
 */
final class AmenityRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            id,
            name,
            icon,
            is_active,
            created_at,
            updated_at
        FROM amenities
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Amenity
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(self::SELECT_COLUMNS . ' WHERE id = :id LIMIT 1');
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The amenity record could not be mapped.');
            }

            return $this->mapAmenity($row);
        } catch (Throwable) {
            throw new DatabaseException('The amenity lookup failed.');
        }
    }

    /**
     * @return list<Amenity>
     */
    public function findAll(): array
    {
        return $this->listing(self::SELECT_COLUMNS . ' ORDER BY name ASC, id ASC');
    }

    /**
     * @return list<Amenity>
     */
    public function findAllActive(): array
    {
        return $this->listing(self::SELECT_COLUMNS . ' WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    public function create(string $name, ?string $icon, bool $active): Amenity
    {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO amenities (name, icon, is_active) VALUES (:name, :icon, :is_active)',
            );
            $statement->execute([
                'name' => $name,
                'icon' => $icon,
                'is_active' => $active ? 1 : 0,
            ]);

            $amenity = $this->findById($this->generatedIdentifier());

            if (!$amenity instanceof Amenity) {
                throw new RuntimeException('The created amenity could not be loaded.');
            }

            return $amenity;
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw RoomCatalogException::duplicateAmenityName($exception);
            }

            throw new DatabaseException('The amenity write failed.');
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The amenity write failed.');
        }
    }

    public function update(int $id, string $name, ?string $icon, bool $active): ?Amenity
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE amenities SET name = :name, icon = :icon, is_active = :is_active WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'name' => $name,
                'icon' => $icon,
                'is_active' => $active ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw RoomCatalogException::duplicateAmenityName($exception);
            }

            throw new DatabaseException('The amenity write failed.');
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The amenity write failed.');
        }
    }

    public function setActive(int $id, bool $active): ?Amenity
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE amenities SET is_active = :is_active WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'is_active' => $active ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The amenity state update failed.');
        }
    }

    /**
     * @return list<Amenity>
     */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The amenity records could not be mapped.');
            }

            $amenities = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The amenity records could not be mapped.');
                }

                $amenities[] = $this->mapAmenity($row);
            }

            return $amenities;
        } catch (Throwable) {
            throw new DatabaseException('The amenity listing failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapAmenity(array $row): Amenity
    {
        return new Amenity(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'name'),
            $this->nullableString($row, 'icon'),
            $this->boolean($row, 'is_active'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated amenity identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated amenity identifier is invalid.');
        }

        return $integer;
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
