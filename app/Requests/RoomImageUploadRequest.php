<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\RoomCatalogException;
use finfo;

/**
 * Holds one validated room-image upload and its normalized metadata.
 */
final readonly class RoomImageUploadRequest
{
    /**
     * @param array<string, list<string>> $allowedMimeExtensions
     */
    private function __construct(
        private int $roomId,
        private UploadedFile $file,
        private string $detectedMediaType,
        private string $extension,
        private int $width,
        private int $height,
        private ?string $alternativeText,
        private ?int $sortOrder,
        private bool $primary,
        private array $allowedMimeExtensions,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function fromInput(
        mixed $roomId,
        mixed $file,
        mixed $alternativeText,
        mixed $sortOrder,
        mixed $primary,
        array $configuration,
    ): self {
        $normalizedRoomId = self::identifier($roomId, 'Room');

        if (!$file instanceof UploadedFile) {
            throw RoomCatalogException::invalidInput('Room image is required.');
        }

        self::validateUploadError($file->error());
        $policy = self::policy($configuration);
        $normalizedAlternativeText = self::normalizeAlternativeText($alternativeText);
        $normalizedSortOrder = self::normalizeSortOrder($sortOrder);
        $normalizedPrimary = RoomCatalogInput::explicitBoolean($primary, 'Primary state');

        if (
            $file->size() <= 0
            || $file->size() > $policy['maximumFileSize']
            || !is_file($file->temporaryPath())
            || !is_readable($file->temporaryPath())
        ) {
            throw RoomCatalogException::invalidInput('Room image file is invalid.');
        }

        $actualSize = filesize($file->temporaryPath());

        if (!is_int($actualSize) || $actualSize <= 0 || $actualSize !== $file->size()) {
            throw RoomCatalogException::invalidInput('Room image file is invalid.');
        }

        $extension = strtolower((string) pathinfo($file->originalName(), PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMediaType = $finfo->file($file->temporaryPath());
        $image = @getimagesize($file->temporaryPath());

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
            throw RoomCatalogException::invalidInput('Room image format is invalid.');
        }

        if (
            $image[0] < $policy['minimumWidth']
            || $image[1] < $policy['minimumHeight']
            || $image[0] > $policy['maximumWidth']
            || $image[1] > $policy['maximumHeight']
        ) {
            throw RoomCatalogException::invalidInput('Room image dimensions are invalid.');
        }

        self::validateDecodability($file->temporaryPath(), $detectedMediaType);

        return new self(
            $normalizedRoomId,
            $file,
            $detectedMediaType,
            $extension,
            $image[0],
            $image[1],
            $normalizedAlternativeText,
            $normalizedSortOrder,
            $normalizedPrimary,
            $policy['allowedMimeExtensions'],
        );
    }

    public static function identifier(mixed $value, string $label): int
    {
        return RoomCatalogInput::identifier($value, $label);
    }

    public function roomId(): int
    {
        return $this->roomId;
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

    public function alternativeText(): ?string
    {
        return $this->alternativeText;
    }

    public function sortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Room image exceeds the upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Room image upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Room image is required.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Room image upload failed.',
            default => 'Room image upload is invalid.',
        };

        throw RoomCatalogException::invalidInput($message);
    }

    private static function validateDecodability(string $path, string $mediaType): void
    {
        if (
            !function_exists('imagecreatefromstring')
            || !function_exists('imagedestroy')
            || !function_exists('imagetypes')
        ) {
            throw RoomCatalogException::invalidInput('Room image decoding is unavailable.');
        }

        $requiredType = match ($mediaType) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            'image/webp' => IMG_WEBP,
            default => 0,
        };

        if ($requiredType === 0 || (imagetypes() & $requiredType) !== $requiredType) {
            throw RoomCatalogException::invalidInput('Room image decoding is unavailable.');
        }

        $contents = @file_get_contents($path);

        if (!is_string($contents) || $contents === '') {
            throw RoomCatalogException::invalidInput('Room image file is invalid.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw RoomCatalogException::invalidInput('Room image content is invalid.');
        }

        imagedestroy($image);
    }

    private static function normalizeAlternativeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw RoomCatalogException::invalidInput('Alternative text is invalid.');
        }

        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);

        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > 255
            || preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1
            || strip_tags($normalized) !== $normalized
        ) {
            throw RoomCatalogException::invalidInput('Alternative text is invalid.');
        }

        return $normalized;
    }

    private static function normalizeSortOrder(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $order = RoomCatalogInput::integer($value, 'Sort order', 0);

        if ($order > 65535) {
            throw RoomCatalogException::invalidInput('Sort order is invalid.');
        }

        return $order;
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

        if (
            !is_array($allowed)
            || $allowed === []
            || !is_int($maximumSize)
            || !is_int($minimumWidth)
            || !is_int($minimumHeight)
            || !is_int($maximumWidth)
            || !is_int($maximumHeight)
            || $maximumSize <= 0
            || $minimumWidth <= 0
            || $minimumHeight <= 0
            || $maximumWidth < $minimumWidth
            || $maximumHeight < $minimumHeight
        ) {
            throw RoomCatalogException::failure();
        }

        foreach ($allowed as $mime => $extensions) {
            if (!is_string($mime) || !is_array($extensions) || $extensions === []) {
                throw RoomCatalogException::failure();
            }

            foreach ($extensions as $extension) {
                if (!is_string($extension) || preg_match('/^[a-z0-9]+$/D', $extension) !== 1) {
                    throw RoomCatalogException::failure();
                }
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
