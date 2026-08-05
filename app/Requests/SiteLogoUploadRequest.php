<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\CmsException;
use finfo;

/** Holds one validated Administrator brand-logo upload. */
final readonly class SiteLogoUploadRequest
{
    private const MAXIMUM_FILE_SIZE_BYTES = 2 * 1024 * 1024;
    private const MINIMUM_DIMENSION_PIXELS = 64;
    private const MAXIMUM_DIMENSION_PIXELS = 4096;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    private function __construct(
        private UploadedFile $file,
        private string $detectedMediaType,
    ) {
    }

    public static function fromFile(mixed $file): self
    {
        if (!$file instanceof UploadedFile) {
            throw CmsException::invalidInput('A logo image is required.');
        }

        self::validateUploadError($file->error());

        $path = $file->temporaryPath();
        $size = $file->size();

        if (
            $size <= 0
            || $size > self::MAXIMUM_FILE_SIZE_BYTES
            || !@is_file($path)
            || @is_link($path)
            || !@is_readable($path)
        ) {
            throw CmsException::invalidInput('The logo image file is invalid.');
        }

        clearstatcache(true, $path);
        $actualSize = @filesize($path);

        if (!is_int($actualSize) || $actualSize !== $size) {
            throw CmsException::invalidInput('The logo image file is invalid.');
        }

        $extension = strtolower((string) pathinfo($file->originalName(), PATHINFO_EXTENSION));
        $detectedMediaType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $image = @getimagesize($path);

        if (
            !is_string($detectedMediaType)
            || !isset(self::MIME_EXTENSIONS[$detectedMediaType])
            || !in_array($extension, self::MIME_EXTENSIONS[$detectedMediaType], true)
            || !is_array($image)
            || !isset($image[0], $image[1], $image['mime'])
            || !is_int($image[0])
            || !is_int($image[1])
            || !is_string($image['mime'])
            || $image['mime'] !== $detectedMediaType
            || $image[0] < self::MINIMUM_DIMENSION_PIXELS
            || $image[1] < self::MINIMUM_DIMENSION_PIXELS
            || $image[0] > self::MAXIMUM_DIMENSION_PIXELS
            || $image[1] > self::MAXIMUM_DIMENSION_PIXELS
        ) {
            throw CmsException::invalidInput('The logo must be a valid JPG, PNG, or WebP image between 64 and 4096 pixels.');
        }

        self::validateDecodability($path);

        return new self($file, $detectedMediaType);
    }

    public function file(): UploadedFile
    {
        return $this->file;
    }

    public function detectedMediaType(): string
    {
        return $this->detectedMediaType;
    }

    private static function validateUploadError(int $error): void
    {
        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The logo exceeds the 2 MiB upload limit.',
            UPLOAD_ERR_PARTIAL => 'The logo upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'A logo image is required.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The logo upload failed.',
            default => 'The logo upload is invalid.',
        };

        throw CmsException::invalidInput($message);
    }

    private static function validateDecodability(string $path): void
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagedestroy')) {
            return;
        }

        $bytes = @file_get_contents($path);

        if (!is_string($bytes) || $bytes === '') {
            throw CmsException::invalidInput('The logo image file is invalid.');
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw CmsException::invalidInput('The logo image content is invalid.');
        }

        imagedestroy($image);
    }
}
