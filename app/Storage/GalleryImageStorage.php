<?php

declare(strict_types=1);

namespace App\Storage;

use App\Requests\GalleryImageUploadRequest;

/**
 * Stores validated gallery image bytes outside the public web root.
 */
interface GalleryImageStorage
{
    public function store(GalleryImageUploadRequest $request): StoredGalleryImage;

    public function remove(string $relativePath): void;
}
