<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\PaymentMethodException;
use finfo;

/**
 * Holds one validated optional Administrator payment-method QR upload.
 */
final readonly class PaymentMethodQrUploadRequest
{
    private const MAXIMUM_FILE_SIZE_BYTES = 5 * 1024 * 1024;
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

    public static function fromFile(mixed $file): ?self
    {
        if ($file === null) {
            return null;
        }

        if (!$file instanceof UploadedFile) {
            throw PaymentMethodException::invalidInput('QR image upload is invalid.');
        }

        if ($file->error() === UPLOAD_ERR_NO_FILE) {
            return null;
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
            throw PaymentMethodException::invalidInput('QR image file is invalid.');
        }

        $actualSize = @filesize($path);

        if (!is_int($actualSize) || $actualSize !== $size) {
            throw PaymentMethodException::invalidInput('QR image file is invalid.');
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
            || $image[0] <= 0
            || $image[1] <= 0
            || $image[0] > self::MAXIMUM_DIMENSION_PIXELS
            || $image[1] > self::MAXIMUM_DIMENSION_PIXELS
        ) {
            throw PaymentMethodException::invalidInput('QR image format is invalid.');
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'QR image exceeds the 5 MiB upload limit.',
            UPLOAD_ERR_PARTIAL => 'QR image upload was incomplete.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'QR image upload failed.',
            default => 'QR image upload is invalid.',
        };

        throw PaymentMethodException::invalidInput($message);
    }

    private static function validateDecodability(string $path): void
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagedestroy')) {
            return;
        }

        $bytes = @file_get_contents($path);

        if (!is_string($bytes) || $bytes === '') {
            throw PaymentMethodException::invalidInput('QR image file is invalid.');
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw PaymentMethodException::invalidInput('QR image content is invalid.');
        }

        imagedestroy($image);
    }
}
