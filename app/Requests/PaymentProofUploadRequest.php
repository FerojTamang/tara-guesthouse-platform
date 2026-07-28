<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\PaymentProofException;
use finfo;

/**
 * Holds one server-validated confidential payment-proof upload.
 */
final readonly class PaymentProofUploadRequest
{
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf'],
    ];

    private const MAXIMUM_FILE_SIZE_BYTES = 5_242_880;

    /**
     * @param array<string, list<string>> $allowedMimeExtensions
     */
    private function __construct(
        private UploadedFile $file,
        private string $detectedMediaType,
        private string $extension,
        private array $allowedMimeExtensions,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function fromInput(mixed $file, array $configuration): self
    {
        if (!$file instanceof UploadedFile) {
            throw PaymentProofException::invalidInput('Payment proof is required.');
        }

        self::validateUploadError($file->error());
        $policy = self::policy($configuration);
        $path = $file->temporaryPath();

        if (
            $file->size() <= 0
            || $file->size() > $policy['maximumFileSize']
            || !is_file($path)
            || is_link($path)
            || !is_readable($path)
        ) {
            throw PaymentProofException::invalidInput('Payment proof file is invalid.');
        }

        clearstatcache(true, $path);
        $actualSize = filesize($path);

        if (!is_int($actualSize) || $actualSize !== $file->size()) {
            throw PaymentProofException::invalidInput('Payment proof file is invalid.');
        }

        $extension = strtolower((string) pathinfo($file->originalName(), PATHINFO_EXTENSION));
        $detectedMediaType = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (
            !is_string($detectedMediaType)
            || !isset($policy['allowedMimeExtensions'][$detectedMediaType])
            || !in_array(
                $extension,
                $policy['allowedMimeExtensions'][$detectedMediaType],
                true,
            )
        ) {
            throw PaymentProofException::invalidInput('Payment proof format is invalid.');
        }

        self::validateContent($path, $file->size(), $detectedMediaType);

        return new self(
            $file,
            $detectedMediaType,
            $extension,
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

    public function byteSize(): int
    {
        return $this->file->size();
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Payment proof exceeds the upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Payment proof upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Payment proof is required.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Payment proof upload failed.',
            default => 'Payment proof upload is invalid.',
        };

        throw PaymentProofException::invalidInput($message);
    }

    private static function validateContent(string $path, int $size, string $mediaType): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw PaymentProofException::invalidInput('Payment proof file is invalid.');
        }

        try {
            $header = @fread($handle, min(16, $size));
            $tailLength = min(2048, $size);

            if (@fseek($handle, -$tailLength, SEEK_END) !== 0) {
                throw PaymentProofException::invalidInput('Payment proof file is invalid.');
            }

            $tail = @stream_get_contents($handle, $tailLength);

            if (!is_string($header) || !is_string($tail)) {
                throw PaymentProofException::invalidInput('Payment proof file is invalid.');
            }
        } finally {
            @fclose($handle);
        }

        $valid = match ($mediaType) {
            'image/jpeg' => self::isValidJpeg($path, $header, $tail),
            'image/png' => self::isValidPng($path, $header, $tail),
            'application/pdf' => self::isValidPdf($header, $tail),
            default => false,
        };

        if (!$valid) {
            throw PaymentProofException::invalidInput('Payment proof content is invalid.');
        }
    }

    private static function isValidJpeg(string $path, string $header, string $tail): bool
    {
        return str_starts_with($header, "\xFF\xD8\xFF")
            && str_ends_with($tail, "\xFF\xD9")
            && self::imageMediaType($path) === 'image/jpeg';
    }

    private static function isValidPng(string $path, string $header, string $tail): bool
    {
        return str_starts_with($header, "\x89PNG\r\n\x1a\n")
            && str_ends_with($tail, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82")
            && self::imageMediaType($path) === 'image/png';
    }

    private static function isValidPdf(string $header, string $tail): bool
    {
        return preg_match('/\A%PDF-(?:1\.[0-7]|2\.0)/D', $header) === 1
            && preg_match('/%%EOF[\x00\x09\x0A\x0C\x0D\x20]*\z/D', $tail) === 1;
    }

    private static function imageMediaType(string $path): ?string
    {
        $image = @getimagesize($path);

        return is_array($image) && is_string($image['mime'] ?? null)
            ? $image['mime']
            : null;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{
     *     allowedMimeExtensions: array<string, list<string>>,
     *     maximumFileSize: int
     * }
     */
    private static function policy(array $configuration): array
    {
        $allowed = $configuration['allowed_mime_extensions'] ?? null;
        $maximumSize = $configuration['maximum_file_size_bytes'] ?? null;

        if (
            $allowed !== self::ALLOWED_MIME_EXTENSIONS
            || $maximumSize !== self::MAXIMUM_FILE_SIZE_BYTES
        ) {
            throw PaymentProofException::system();
        }

        return [
            'allowedMimeExtensions' => self::ALLOWED_MIME_EXTENSIONS,
            'maximumFileSize' => self::MAXIMUM_FILE_SIZE_BYTES,
        ];
    }
}
