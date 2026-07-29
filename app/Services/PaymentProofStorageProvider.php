<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentProofException;
use App\Storage\LocalPaymentProofStorage;
use App\Storage\PaymentProofReader;
use App\Storage\PaymentProofStorage;
use Throwable;

/**
 * Loads the approved private proof policy and constructs its local storage.
 */
final class PaymentProofStorageProvider
{
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf'],
    ];

    private const MAXIMUM_FILE_SIZE_BYTES = 5_242_880;
    private const FILESYSTEM_ROOT = 'storage/private/payment-proofs';
    private const DATABASE_PATH_PREFIX = 'payment-proofs/';

    private ?PaymentProofStorage $storage = null;

    /** @var null|array<string, mixed> */
    private ?array $uploadConfiguration = null;

    public function __construct(private readonly string $basePath)
    {
    }

    public function reader(): PaymentProofReader
    {
        $storage = $this->storage();

        if (!$storage instanceof PaymentProofReader) {
            throw PaymentProofException::system();
        }

        return $storage;
    }

    public function storage(): PaymentProofStorage
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        try {
            $configuration = $this->uploadConfiguration();
            $root = $this->basePath
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, self::FILESYSTEM_ROOT);

            $this->storage = new LocalPaymentProofStorage(
                $root,
                $configuration['maximum_file_size_bytes'],
            );

            return $this->storage;
        } catch (PaymentProofException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw PaymentProofException::system($failure);
        }
    }

    /** @return array<string, mixed> */
    public function uploadConfiguration(): array
    {
        if ($this->uploadConfiguration !== null) {
            return $this->uploadConfiguration;
        }

        try {
            $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php';

            if (!is_file($path)) {
                throw PaymentProofException::system();
            }

            $configuration = require $path;
            $paymentProofs = is_array($configuration)
                ? ($configuration['payment_proofs'] ?? null)
                : null;

            if (
                !is_array($paymentProofs)
                || array_keys($paymentProofs) !== [
                    'allowed_mime_extensions',
                    'maximum_file_size_bytes',
                    'filesystem_root',
                    'database_path_prefix',
                ]
                || ($paymentProofs['allowed_mime_extensions'] ?? null) !== self::ALLOWED_MIME_EXTENSIONS
                || ($paymentProofs['maximum_file_size_bytes'] ?? null) !== self::MAXIMUM_FILE_SIZE_BYTES
                || ($paymentProofs['filesystem_root'] ?? null) !== self::FILESYSTEM_ROOT
                || ($paymentProofs['database_path_prefix'] ?? null) !== self::DATABASE_PATH_PREFIX
            ) {
                throw PaymentProofException::system();
            }

            $this->uploadConfiguration = $paymentProofs;

            return $this->uploadConfiguration;
        } catch (PaymentProofException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw PaymentProofException::system($failure);
        }
    }
}
