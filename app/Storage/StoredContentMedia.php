<?php

declare(strict_types=1);

namespace App\Storage;

use InvalidArgumentException;

/** Describes one safely stored content image without exposing its physical path. */
final readonly class StoredContentMedia
{
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
        private string $sha256,
    ) {
        if (
            !isset(self::MEDIA_TYPES[$extension])
            || self::MEDIA_TYPES[$extension] !== $mediaType
            || preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) !== 1
            || $relativePath !== 'uploads/content/' . $filename
            || $byteSize <= 0
            || $width <= 0
            || $height <= 0
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
        ) {
            throw new InvalidArgumentException('Stored content media data is invalid.');
        }
    }

    public function relativePath(): string { return $this->relativePath; }
    public function filename(): string { return $this->filename; }
    public function extension(): string { return $this->extension; }
    public function mediaType(): string { return $this->mediaType; }
    public function byteSize(): int { return $this->byteSize; }
    public function width(): int { return $this->width; }
    public function height(): int { return $this->height; }
    public function sha256(): string { return $this->sha256; }
}
