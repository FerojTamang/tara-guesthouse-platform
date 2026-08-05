<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ContentMedia;

/** Defines persistence operations for reusable content images. */
interface ContentMediaRepository
{
    /** @return list<ContentMedia> */
    public function findAllActive(): array;
    public function findActiveById(int $id): ?ContentMedia;
    public function findActiveByIdForShare(int $id): ?ContentMedia;
    public function findActiveByIdForUpdate(int $id): ?ContentMedia;
    public function findActiveByChecksum(string $sha256): ?ContentMedia;

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
    ): ContentMedia;

    public function updateMetadata(
        int $id,
        ?string $title,
        string $altText,
        ?string $caption,
    ): ?ContentMedia;

    public function softDelete(int $id): bool;
}
