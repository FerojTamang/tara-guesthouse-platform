<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\RoomImage;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides read-only persistence access to ordered room-image metadata.
 */
final class RoomImageRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            id,
            room_id,
            file_path,
            alt_text,
            sort_order,
            is_primary,
            created_at
        FROM room_images
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?RoomImage
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE id = :lookup LIMIT 1',
            $id,
            'The room image lookup failed.',
        );
    }

    /**
     * @return list<RoomImage>
     */
    public function findAllByRoomId(int $roomId): array
    {
        if ($roomId <= 0) {
            return [];
        }

        return $this->listing(
            self::SELECT_COLUMNS . ' WHERE room_id = :room_id ORDER BY sort_order ASC, id ASC',
            $roomId,
        );
    }

    /**
     * Returns the first deterministic primary image when inconsistent legacy data contains more than one.
     */
    public function findPrimaryByRoomId(int $roomId): ?RoomImage
    {
        if ($roomId <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS
                . ' WHERE room_id = :lookup AND is_primary = 1 ORDER BY sort_order ASC, id ASC LIMIT 1',
            $roomId,
            'The primary room image lookup failed.',
        );
    }

    private function lookup(string $sql, int $value, string $failureMessage): ?RoomImage
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The room image record could not be mapped.');
            }

            return $this->mapRoomImage($row);
        } catch (Throwable) {
            throw new DatabaseException($failureMessage);
        }
    }

    /**
     * @return list<RoomImage>
     */
    private function listing(string $sql, int $roomId): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The room image records could not be mapped.');
            }

            $images = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The room image records could not be mapped.');
                }

                $images[] = $this->mapRoomImage($row);
            }

            return $images;
        } catch (Throwable) {
            throw new DatabaseException('The room image listing failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRoomImage(array $row): RoomImage
    {
        return new RoomImage(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'room_id'),
            $this->string($row, 'file_path'),
            $this->nullableString($row, 'alt_text'),
            $this->nonNegativeInteger($row, 'sort_order'),
            $this->boolean($row, 'is_primary'),
            $this->dateTime($row, 'created_at'),
        );
    }
}
