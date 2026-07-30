<?php

declare(strict_types=1);

namespace App\Storage;

use InvalidArgumentException;

/**
 * Contains validated proof bytes without disclosing the physical storage path.
 */
final readonly class RetrievedPaymentProof
{
    private const MEDIA_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];

    public function __construct(
        private string $bytes,
        private string $extension,
        private string $mediaType,
    ) {
        if (
            $bytes === ''
            || !isset(self::MEDIA_TYPES[$extension])
            || self::MEDIA_TYPES[$extension] !== $mediaType
        ) {
            throw new InvalidArgumentException('Retrieved payment proof is invalid.');
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

    public function byteSize(): int
    {
        return strlen($this->bytes);
    }
}
