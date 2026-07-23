<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Represents one immutable file normalized at the PHP HTTP boundary.
 */
final readonly class UploadedFile
{
    private const VALID_ERRORS = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    private string $originalName;

    public function __construct(
        string $originalName,
        private string $clientMediaType,
        private string $temporaryPath,
        private int $error,
        private int $size,
    ) {
        if (
            str_contains($originalName, "\0")
            || str_contains($clientMediaType, "\0")
            || str_contains($temporaryPath, "\0")
            || $size < 0
            || !in_array($error, self::VALID_ERRORS, true)
        ) {
            throw new InvalidArgumentException('Uploaded file data is invalid.');
        }

        $normalizedName = basename(str_replace('\\', '/', $originalName));

        if ($error === UPLOAD_ERR_OK && ($normalizedName === '' || $temporaryPath === '')) {
            throw new InvalidArgumentException('Uploaded file data is invalid.');
        }

        $this->originalName = $normalizedName;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function clientMediaType(): string
    {
        return $this->clientMediaType;
    }

    public function temporaryPath(): string
    {
        return $this->temporaryPath;
    }

    public function error(): int
    {
        return $this->error;
    }

    public function size(): int
    {
        return $this->size;
    }
}
