<?php

declare(strict_types=1);

namespace App\Storage;

use InvalidArgumentException;

/**
 * Contains one immutable gallery image after private-storage revalidation.
 */
final readonly class RetrievedGalleryImage
{
    public function __construct(
        private string $bytes,
        private string $extension,
        private string $mediaType,
        private int $width,
        private int $height,
    ) {
        $expected = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };

        if (
            $bytes === ''
            || $expected !== $mediaType
            || $width <= 0
            || $height <= 0
        ) {
            throw new InvalidArgumentException('Retrieved gallery image data is invalid.');
        }
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function size(): int
    {
        return strlen($this->bytes);
    }
}
