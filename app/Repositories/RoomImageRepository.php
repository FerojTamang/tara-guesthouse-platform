<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\RoomImage;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides persistence access to ordered room-image metadata.
 */
final class RoomImageRepository implements RoomImageCatalogRepository
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

    public function countByRoomId(int $roomId): int
    {
        if ($roomId <= 0) {
            return 0;
        }

        try {
            $statement = $this->connection->prepare(
                'SELECT COUNT(*) FROM room_images WHERE room_id = :room_id',
            );
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();
            $count = $statement->fetchColumn();

            if (!is_int($count) && (!is_string($count) || preg_match('/^[0-9]+$/D', $count) !== 1)) {
                throw new RuntimeException('The room image count could not be mapped.');
            }

            return (int) $count;
        } catch (Throwable) {
            throw new DatabaseException('The room image count failed.');
        }
    }

    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage
    {
        if ($imageId <= 0 || $roomId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT_COLUMNS . ' WHERE id = :image_id AND room_id = :room_id LIMIT 1',
            );
            $statement->bindValue('image_id', $imageId, PDO::PARAM_INT);
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
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
            throw new DatabaseException('The room image lookup failed.');
        }
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

        return $this->findPrimaryByRoomIds([$roomId])[$roomId] ?? null;
    }

    /**
     * @param list<int> $roomIds
     * @return array<int, RoomImage>
     */
    public function findPrimaryByRoomIds(array $roomIds): array
    {
        $identifiers = $this->normalizeIdentifiers($roomIds);
        if ($identifiers === []) {
            return [];
        }

        try {
            $placeholders = [];
            foreach (array_keys($identifiers) as $index) {
                $placeholders[] = ':room_id_' . $index;
            }

            $statement = $this->connection->prepare(
                self::SELECT_COLUMNS
                    . ' WHERE room_id IN (' . implode(', ', $placeholders) . ') AND is_primary = 1'
                    . ' ORDER BY room_id ASC, sort_order ASC, id ASC',
            );
            foreach ($identifiers as $index => $roomId) {
                $statement->bindValue('room_id_' . $index, $roomId, PDO::PARAM_INT);
            }
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The primary room images could not be mapped.');
            }

            $images = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The primary room images could not be mapped.');
                }
                $image = $this->mapRoomImage($row);
                $images[$image->roomId()] ??= $image;
            }

            return $images;
        } catch (Throwable) {
            throw new DatabaseException('The primary room image lookup failed.');
        }
    }

    public function create(
        int $roomId,
        string $filePath,
        ?string $altText,
        int $sortOrder,
        bool $primary,
    ): RoomImage {
        if ($roomId <= 0 || $sortOrder < 0 || !$this->isSafeRelativePath($filePath)) {
            throw new DatabaseException('The room image write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO room_images (room_id, file_path, alt_text, sort_order, is_primary) '
                . 'VALUES (:room_id, :file_path, :alt_text, :sort_order, :is_primary)',
            );
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->bindValue('file_path', $filePath, PDO::PARAM_STR);
            $statement->bindValue('alt_text', $altText, $altText === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('sort_order', $sortOrder, PDO::PARAM_INT);
            $statement->bindValue('is_primary', $primary ? 1 : 0, PDO::PARAM_INT);
            $statement->execute();

            $image = $this->findByIdAndRoomId($this->generatedIdentifier(), $roomId);

            if (!$image instanceof RoomImage) {
                throw new RuntimeException('The created room image could not be loaded.');
            }

            return $image;
        } catch (Throwable) {
            throw new DatabaseException('The room image write failed.');
        }
    }

    public function updateMetadata(
        int $imageId,
        int $roomId,
        ?string $altText,
        int $sortOrder,
    ): ?RoomImage {
        if ($imageId <= 0 || $roomId <= 0) {
            return null;
        }

        if ($sortOrder < 0) {
            throw new DatabaseException('The room image metadata update failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE room_images SET alt_text = :alt_text, sort_order = :sort_order '
                . 'WHERE id = :image_id AND room_id = :room_id',
            );
            $statement->bindValue('alt_text', $altText, $altText === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('sort_order', $sortOrder, PDO::PARAM_INT);
            $statement->bindValue('image_id', $imageId, PDO::PARAM_INT);
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();

            return $this->findByIdAndRoomId($imageId, $roomId);
        } catch (Throwable) {
            throw new DatabaseException('The room image metadata update failed.');
        }
    }

    public function clearPrimaryByRoomId(int $roomId): void
    {
        if ($roomId <= 0) {
            throw new DatabaseException('The room image primary update failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE room_images SET is_primary = 0 WHERE room_id = :room_id',
            );
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();
        } catch (Throwable) {
            throw new DatabaseException('The room image primary update failed.');
        }
    }

    public function setPrimary(int $imageId, int $roomId): ?RoomImage
    {
        if ($imageId <= 0 || $roomId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE room_images SET is_primary = 1 '
                . 'WHERE id = :image_id AND room_id = :room_id',
            );
            $statement->bindValue('image_id', $imageId, PDO::PARAM_INT);
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();

            return $this->findByIdAndRoomId($imageId, $roomId);
        } catch (Throwable) {
            throw new DatabaseException('The room image primary update failed.');
        }
    }

    public function delete(int $imageId, int $roomId): ?RoomImage
    {
        if ($imageId <= 0 || $roomId <= 0) {
            return null;
        }

        try {
            $image = $this->findByIdAndRoomId($imageId, $roomId);

            if (!$image instanceof RoomImage) {
                return null;
            }

            $statement = $this->connection->prepare(
                'DELETE FROM room_images WHERE id = :image_id AND room_id = :room_id',
            );
            $statement->bindValue('image_id', $imageId, PDO::PARAM_INT);
            $statement->bindValue('room_id', $roomId, PDO::PARAM_INT);
            $statement->execute();

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The room image was not deleted.');
            }

            return $image;
        } catch (Throwable) {
            throw new DatabaseException('The room image deletion failed.');
        }
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

    /**
     * @param list<int> $identifiers
     * @return list<int>
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        $normalized = [];
        foreach ($identifiers as $identifier) {
            if (is_int($identifier) && $identifier > 0) {
                $normalized[$identifier] = $identifier;
            }
        }

        return array_values($normalized);
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated room image identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated room image identifier is invalid.');
        }

        return $integer;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if (
            $path === ''
            || !mb_check_encoding($path, 'UTF-8')
            || mb_strlen($path, 'UTF-8') > 255
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_contains($path, '\\')
            || str_contains($path, '//')
            || str_contains($path, "\0")
        ) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
