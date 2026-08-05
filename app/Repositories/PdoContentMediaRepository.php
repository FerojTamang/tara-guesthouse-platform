<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ContentMediaException;
use App\Models\ContentMedia;
use PDO;
use PDOException;
use Throwable;

/** Provides PDO persistence for reusable content images. */
final class PdoContentMediaRepository implements ContentMediaRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT cm.id, cm.storage_path, cm.original_name, cm.mime_type, cm.file_extension,
               cm.file_size_bytes, cm.width_pixels, cm.height_pixels, cm.checksum_sha256,
               cm.title, cm.alt_text, cm.caption, cm.uploaded_by, cm.created_at,
               cm.updated_at, cm.deleted_at,
               (
                   (SELECT COUNT(*) FROM page_block_media pbm WHERE pbm.media_id = cm.id)
                   +
                   (SELECT COUNT(*) FROM page_content_profiles pcp WHERE pcp.featured_media_id = cm.id)
                   +
                   (SELECT COUNT(*) FROM page_content_profiles pcp WHERE pcp.social_media_id = cm.id)
               ) AS usage_count
        FROM content_media cm
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAllActive(): array
    {
        try {
            $statement = $this->connection->query(
                self::SELECT . ' WHERE cm.deleted_at IS NULL ORDER BY cm.created_at DESC, cm.id DESC',
            );
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                throw ContentMediaException::failure();
            }
            return array_map($this->map(...), $rows);
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function findActiveById(int $id): ?ContentMedia
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(self::SELECT . ' WHERE cm.id = :lookup AND cm.deleted_at IS NULL LIMIT 1', $id);
    }

    public function findActiveByIdForShare(int $id): ?ContentMedia
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT . ' WHERE cm.id = :lookup AND cm.deleted_at IS NULL LIMIT 1 LOCK IN SHARE MODE',
            $id,
        );
    }

    public function findActiveByIdForUpdate(int $id): ?ContentMedia
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT . ' WHERE cm.id = :lookup AND cm.deleted_at IS NULL LIMIT 1 FOR UPDATE',
            $id,
        );
    }

    public function findActiveByChecksum(string $sha256): ?ContentMedia
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            return null;
        }

        return $this->lookup(
            self::SELECT . ' WHERE cm.checksum_sha256 = :lookup AND cm.deleted_at IS NULL ORDER BY cm.id ASC LIMIT 1',
            $sha256,
            PDO::PARAM_STR,
        );
    }

    public function create(
        string $storagePath,
        string $originalName,
        string $mimeType,
        string $extension,
        int $byteSize,
        int $width,
        int $height,
        string $sha256,
        ?string $title,
        string $altText,
        ?string $caption,
        int $uploadedBy,
    ): ContentMedia {
        try {
            $statement = $this->connection->prepare(<<<'SQL'
                INSERT INTO content_media (
                    storage_path, original_name, mime_type, file_extension, file_size_bytes,
                    width_pixels, height_pixels, checksum_sha256, active_checksum_sha256,
                    title, alt_text, caption, uploaded_by
                ) VALUES (
                    :storage_path, :original_name, :mime_type, :file_extension, :file_size_bytes,
                    :width_pixels, :height_pixels, :checksum_sha256, :active_checksum_sha256,
                    :title, :alt_text, :caption, :uploaded_by
                )
                SQL);
            $statement->execute([
                'storage_path' => $storagePath,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'file_extension' => $extension,
                'file_size_bytes' => $byteSize,
                'width_pixels' => $width,
                'height_pixels' => $height,
                'checksum_sha256' => $sha256,
                'active_checksum_sha256' => $sha256,
                'title' => $title,
                'alt_text' => $altText,
                'caption' => $caption,
                'uploaded_by' => $uploadedBy,
            ]);

            $id = (int) $this->connection->lastInsertId();
            $media = $this->findActiveById($id);
            if (!$media instanceof ContentMedia) {
                throw ContentMediaException::failure();
            }
            return $media;
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (PDOException $failure) {
            if (
                (string) $failure->getCode() === '23000'
                && (int) ($failure->errorInfo[1] ?? 0) === 1062
                && str_contains(strtolower($failure->getMessage()), 'uq_content_media_active_checksum')
            ) {
                throw ContentMediaException::conflict('This image already exists in the media library.');
            }
            throw ContentMediaException::failure($failure);
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function updateMetadata(int $id, ?string $title, string $altText, ?string $caption): ?ContentMedia
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE content_media SET title = :title, alt_text = :alt_text, caption = :caption '
                . 'WHERE id = :id AND deleted_at IS NULL',
            );
            $statement->execute([
                'id' => $id,
                'title' => $title,
                'alt_text' => $altText,
                'caption' => $caption,
            ]);
            return $this->findActiveById($id);
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function softDelete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE content_media '
                . 'SET active_checksum_sha256 = NULL, deleted_at = UTC_TIMESTAMP() '
                . 'WHERE id = :id AND deleted_at IS NULL',
            );
            $statement->execute(['id' => $id]);
            return $statement->rowCount() === 1;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    private function lookup(string $sql, int|string $lookup, int $type = PDO::PARAM_INT): ?ContentMedia
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $lookup, $type);
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $this->map($row) : null;
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): ContentMedia
    {
        try {
            return new ContentMedia(
                $this->positiveInteger($row, 'id'),
                $this->string($row, 'storage_path'),
                $this->string($row, 'original_name'),
                $this->string($row, 'mime_type'),
                $this->string($row, 'file_extension'),
                $this->positiveInteger($row, 'file_size_bytes'),
                $this->positiveInteger($row, 'width_pixels'),
                $this->positiveInteger($row, 'height_pixels'),
                $this->string($row, 'checksum_sha256'),
                $this->nullableString($row, 'title'),
                $this->string($row, 'alt_text'),
                $this->nullableString($row, 'caption'),
                $this->positiveInteger($row, 'uploaded_by'),
                $this->dateTime($row, 'created_at'),
                $this->dateTime($row, 'updated_at'),
                $this->nullableDateTime($row, 'deleted_at'),
                $this->nonNegativeInteger($row, 'usage_count'),
            );
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }
}
