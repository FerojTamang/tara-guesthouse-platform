<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Amenity;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides read-only persistence access to the amenity catalogue.
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
}
