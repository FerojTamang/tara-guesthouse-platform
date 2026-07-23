<?php

declare(strict_types=1);

namespace App\Storage;

use InvalidArgumentException;

/**
 * Describes one safely stored room image without exposing its physical path.
 */
final readonly class StoredRoomImage
{
    private const PATH_PREFIX = 'uploads/rooms/';
    private const EXTENSIONS = ['jpg', 'png', 'webp'];

    public function __construct(
        private string $relativePath,
        private string $filename,
        private string $extension,
        private int $byteSize,
    ) {
        if (
            !in_array($extension, self::EXTENSIONS, true)
            || preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) !== 1
            || !str_ends_with($filename, '.' . $extension)
            || $relativePath !== self::PATH_PREFIX . $filename
            || $byteSize <= 0
        ) {
            throw new InvalidArgumentException('Stored room image data is invalid.');
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

    public function byteSize(): int
    {
        return $this->byteSize;
    }
}
