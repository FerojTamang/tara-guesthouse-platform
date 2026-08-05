<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\ContentMediaException;
use finfo;

/** Holds one content image after strict server-side image validation. */
final readonly class ContentMediaUploadRequest
{
    private function __construct(
        private UploadedFile $file,
        private string $originalName,
        private string $detectedMediaType,
        private string $extension,
        private int $width,
        private int $height,
        private string $sha256,
        private ContentMediaMetadataRequest $metadata,
    ) {
    }

    /** @param array<string, mixed> $configuration */
    public static function fromInput(
        mixed $file,
        mixed $title,
        mixed $altText,
        mixed $caption,
        array $configuration,
    ): self {
        if (!$file instanceof UploadedFile) {
            throw ContentMediaException::invalidInput('Content image is required.');
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
            throw ContentMediaException::invalidInput('Content image file is invalid.');
        }

        clearstatcache(true, $path);
        $actualSize = @filesize($path);

        if (!is_int($actualSize) || $actualSize !== $file->size()) {
            throw ContentMediaException::invalidInput('Content image file is invalid.');
        }

        $originalName = self::normalizeOriginalName($file->originalName());
        $submittedExtension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
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
            || !in_array($submittedExtension, $policy['allowedMimeExtensions'][$detectedMediaType], true)
        ) {
            throw ContentMediaException::invalidInput('Content image format is invalid.');
        }

        if (
            $image[0] < $policy['minimumWidth']
            || $image[1] < $policy['minimumHeight']
            || $image[0] > $policy['maximumWidth']
            || $image[1] > $policy['maximumHeight']
        ) {
            throw ContentMediaException::invalidInput('Content image dimensions are invalid.');
        }

        self::validateDecodability($path, $detectedMediaType);
        $sha256 = @hash_file('sha256', $path);

        if (!is_string($sha256) || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw ContentMediaException::invalidInput('Content image file is invalid.');
        }

        clearstatcache(true, $path);
        if (@filesize($path) !== $file->size()) {
            throw ContentMediaException::invalidInput('Content image file is invalid.');
        }

        $extension = match ($detectedMediaType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ContentMediaException::invalidInput('Content image format is invalid.'),
        };

        return new self(
            $file,
            $originalName,
            $detectedMediaType,
            $extension,
            $image[0],
            $image[1],
            $sha256,
            ContentMediaMetadataRequest::fromInput($title, $altText, $caption),
        );
    }

    public function file(): UploadedFile { return $this->file; }
    public function originalName(): string { return $this->originalName; }
    public function detectedMediaType(): string { return $this->detectedMediaType; }
    public function extension(): string { return $this->extension; }
    public function width(): int { return $this->width; }
    public function height(): int { return $this->height; }
    public function sha256(): string { return $this->sha256; }
    public function metadata(): ContentMediaMetadataRequest { return $this->metadata; }

    private static function normalizeOriginalName(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw ContentMediaException::invalidInput('Content image filename is invalid.');
        }

        $value = trim(basename(str_replace('\\', '/', $value)));

        if ($value === '' || mb_strlen($value, 'UTF-8') > 255) {
            throw ContentMediaException::invalidInput('Content image filename is invalid.');
        }

        return $value;
    }

    private static function validateUploadError(int $error): void
    {
        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Content image exceeds the upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Content image upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Content image is required.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Content image upload failed.',
            default => 'Content image upload is invalid.',
        };

        throw ContentMediaException::invalidInput($message);
    }

    private static function validateDecodability(string $path, string $mediaType): void
    {
        if (
            !function_exists('imagecreatefromstring')
            || !function_exists('imagedestroy')
            || !function_exists('imagetypes')
        ) {
            throw ContentMediaException::invalidInput('Content image decoding is unavailable.');
        }

        $requiredType = match ($mediaType) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            'image/webp' => IMG_WEBP,
            default => 0,
        };

        if ($requiredType === 0 || (imagetypes() & $requiredType) !== $requiredType) {
            throw ContentMediaException::invalidInput('Content image decoding is unavailable.');
        }

        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            throw ContentMediaException::invalidInput('Content image file is invalid.');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw ContentMediaException::invalidInput('Content image content is invalid.');
        }

        imagedestroy($image);
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{
     *   allowedMimeExtensions: array<string, list<string>>,
     *   maximumFileSize: int,
     *   minimumWidth: int,
     *   minimumHeight: int,
     *   maximumWidth: int,
     *   maximumHeight: int
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

        $expected = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        if (
            $allowed !== $expected
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
            throw ContentMediaException::failure();
        }

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
