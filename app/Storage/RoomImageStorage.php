<?php

declare(strict_types=1);

namespace App\Storage;

use App\Core\UploadedFile;

/**
 * Stores validated room-image bytes without database or workflow concerns.
 */
interface RoomImageStorage
{
    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage;

    public function remove(string $relativePath): void;
}
