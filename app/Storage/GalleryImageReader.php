<?php

declare(strict_types=1);

namespace App\Storage;

/**
 * Reads one validated gallery image from private storage for public delivery.
 */
interface GalleryImageReader
{
    public function read(string $relativePath): ?RetrievedGalleryImage;
}
