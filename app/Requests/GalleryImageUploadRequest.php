<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\GalleryImageException;
use finfo;

/**
 * Holds one gallery image after server-side format and decodability checks.
 */
final readonly class GalleryImageUploadRequest
{
    /**
     * @param array<string, list<string>> $allowedMimeExtensions
     */
    private function __construct(
        private UploadedFile $file,
        private string $detectedMediaType,
        private string $extension,
        private int $width,
        private int $height,
        private string $sha256,
        private array $allowedMimeExtensions,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function fromInput(mixed $file, array $configuration): self
    {
        if (!$file instanceof UploadedFile) {
            throw GalleryImageException::invalidInput('Gallery image is required.');
        }

        self::validateUploadError($file->error());
        $policy = self::policy($configuration);
        $path = $file->temporaryPath();

        if (
            $file->size() <= 0
            || $file->size() > $policy['maximumFileSize']
            || !@is_file($path)
            || @is_link($path)
            || !@is_readable($path)
        ) {
            throw GalleryImageException::invalidInput('Gallery image file is invalid.');
        }

        clearstatcache(true, $path);
        $actualSize = @filesize($path);

        if (!is_int($actualSize) || $actualSize <= 0 || $actualSize !== $file->size()) {
            throw GalleryImageException::invalidInput('Gallery image file is invalid.');
        }

        $extension = strtolower((string) pathinfo($file->originalName(), PATHINFO_EXTENSION));
        $detectedMediaType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $image = @getimagesize($path);

        if (
            !is_string($detectedMediaType)
            || !is_array($image)
            || !isset($image[0], $image[1], $image['mime'])
            || !is_int($image[0])
            || !is_int($image[1])
            || !is_string($image['mime'])
            || $image['mime'] !== $detectedMediaType
            || !isset($policy['allowedMimeExtensions'][$detectedMediaType])
            || !in_array($extension, $policy['allowedMimeExtensions'][$detectedMediaType], true)
        ) {
            throw GalleryImageException::invalidInput('Gallery image format is invalid.');
        }

        if (
            $image[0] < $policy['minimumWidth']
            || $image[1] < $policy['minimumHeight']
            || $image[0] > $policy['maximumWidth']
            || $image[1] > $policy['maximumHeight']
        ) {
            throw GalleryImageException::invalidInput('Gallery image dimensions are invalid.');
        }

        $sha256 = @hash_file('sha256', $path);

        if (!is_string($sha256) || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw GalleryImageException::invalidInput('Gallery image file is invalid.');
        }

        self::validateDecodability($path, $detectedMediaType);
        clearstatcache(true, $path);

        if (@filesize($path) !== $file->size()) {
            throw GalleryImageException::invalidInput('Gallery image file is invalid.');
        }

        return new self(
            $file,
            $detectedMediaType,
            $extension,
            $image[0],
            $image[1],
            $sha256,
            $policy['allowedMimeExtensions'],
        );
    }

    public function file(): UploadedFile
    {
        return $this->file;
    }

    public function detectedMediaType(): string
    {
        return $this->detectedMediaType;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    /** @return array<string, list<string>> */
    public function allowedMimeExtensions(): array
    {
        return $this->allowedMimeExtensions;
    }

    private static function validateUploadError(int $error): void
    {
        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Gallery image exceeds the upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Gallery image upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Gallery image is required.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Gallery image upload failed.',
            default => 'Gallery image upload is invalid.',
        };

        throw GalleryImageException::invalidInput($message);
    }

    private static function validateDecodability(string $path, string $mediaType): void
    {
        if (
            !function_exists('imagecreatefromstring')
            || !function_exists('imagedestroy')
            || !function_exists('imagetypes')
        ) {
            throw GalleryImageException::invalidInput('Gallery image decoding is unavailable.');
        }

        $requiredType = match ($mediaType) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            'image/webp' => IMG_WEBP,
            default => 0,
        };

        if ($requiredType === 0 || (imagetypes() & $requiredType) !== $requiredType) {
            throw GalleryImageException::invalidInput('Gallery image decoding is unavailable.');
        }

        $contents = @file_get_contents($path);

        if (!is_string($contents) || $contents === '') {
            throw GalleryImageException::invalidInput('Gallery image file is invalid.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw GalleryImageException::invalidInput('Gallery image content is invalid.');
        }

        imagedestroy($image);
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{
     *     allowedMimeExtensions: array<string, list<string>>,
     *     maximumFileSize: int,
     *     minimumWidth: int,
     *     minimumHeight: int,
     *     maximumWidth: int,
     *     maximumHeight: int
     * }
     */
    private static function policy(array $configuration): array
    {
        $allowed = $configuration['allowed_mime_extensions'] ?? null;
        $maximumSize = $configuration['maximum_file_size_bytes'] ?? null;
        $minimumWidth = $configuration['minimum_width_pixels'] ?? null;
        $minimumHeight = $configuration['minimum_height_pixels'] ?? null;
        $maximumWidth = $configuration['maximum_width_pixels'] ?? null;
        $maximumHeight = $configuration['maximum_height_pixels'] ?? null;
        $decodability = $configuration['require_decodability'] ?? null;

        if (
            !is_array($allowed)
            || $allowed === []
            || !is_int($maximumSize)
            || !is_int($minimumWidth)
            || !is_int($minimumHeight)
            || !is_int($maximumWidth)
            || !is_int($maximumHeight)
            || $decodability !== true
            || $maximumSize <= 0
            || $minimumWidth <= 0
            || $minimumHeight <= 0
            || $maximumWidth < $minimumWidth
            || $maximumHeight < $minimumHeight
        ) {
            throw GalleryImageException::system();
        }

        $supported = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        foreach ($allowed as $mime => $extensions) {
            if (
                !is_string($mime)
                || !isset($supported[$mime])
                || !is_array($extensions)
                || $extensions === []
                || array_values($extensions) !== $supported[$mime]
            ) {
                throw GalleryImageException::system();
            }
        }

        /** @var array<string, list<string>> $allowed */
        return [
            'allowedMimeExtensions' => $allowed,
            'maximumFileSize' => $maximumSize,
            'minimumWidth' => $minimumWidth,
            'minimumHeight' => $minimumHeight,
            'maximumWidth' => $maximumWidth,
            'maximumHeight' => $maximumHeight,
        ];
    }
}
