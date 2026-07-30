<?php

declare(strict_types=1);

namespace App\Storage;

use InvalidArgumentException;

/**
 * Describes one safely stored gallery image without exposing its physical path.
 */
final readonly class StoredGalleryImage
{
    private const PATH_PREFIX = 'gallery/';
    private const MEDIA_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private string $relativePath,
        private string $filename,
        private string $extension,
        private string $mediaType,
        private int $byteSize,
        private int $width,
        private int $height,
    ) {
        if (
            !isset(self::MEDIA_TYPES[$extension])
            || self::MEDIA_TYPES[$extension] !== $mediaType
            || preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) !== 1
            || !str_ends_with($filename, '.' . $extension)
            || $relativePath !== self::PATH_PREFIX . $filename
            || $byteSize <= 0
            || $width <= 0
            || $height <= 0
        ) {
            throw new InvalidArgumentException('Stored gallery image data is invalid.');
        }
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function byteSize(): int
    {
        return $this->byteSize;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }
}
