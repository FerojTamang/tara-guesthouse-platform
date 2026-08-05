<?php

declare(strict_types=1);

namespace App\Storage;

use App\Requests\PaymentMethodQrUploadRequest;
use RuntimeException;
use Throwable;

/**
 * Stores validated QR images in the dedicated public payment-method directory.
 */
final readonly class LocalPaymentMethodQrStorage implements PaymentMethodQrStorage
{
    private const PATH_PREFIX = 'payment-methods/';
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private string $root;

    public function __construct(string $root)
    {
        clearstatcache(true, $root);

        if (
            $root === ''
            || str_contains($root, "\0")
            || !self::isAbsolutePath($root)
            || !@is_dir($root)
            || @is_link($root)
            || !@is_writable($root)
        ) {
            throw new RuntimeException('Payment method QR storage is unavailable.');
        }

        $resolved = @realpath($root);

        if (!is_string($resolved) || !self::pathsEqual($root, $resolved)) {
            throw new RuntimeException('Payment method QR storage is unavailable.');
        }

        $this->root = rtrim($resolved, '\\/');
    }

    public function store(PaymentMethodQrUploadRequest $request): string
    {
        $extension = self::MIME_EXTENSIONS[$request->detectedMediaType()] ?? null;

        if ($extension === null) {
            throw new RuntimeException('Payment method QR storage failed.');
        }

        $file = $request->file();
        $sourcePath = $file->temporaryPath();
        $expectedSize = $file->size();

        if (
            $expectedSize <= 0
            || !@is_file($sourcePath)
            || @is_link($sourcePath)
            || !@is_readable($sourcePath)
            || @filesize($sourcePath) !== $expectedSize
        ) {
            throw new RuntimeException('Payment method QR storage failed.');
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $filename = bin2hex(random_bytes(32)) . '.' . $extension;
            $destinationPath = $this->root . DIRECTORY_SEPARATOR . $filename;
            $destination = @fopen($destinationPath, 'x+b');

            if ($destination === false) {
                if (@file_exists($destinationPath)) {
                    continue;
                }

                throw new RuntimeException('Payment method QR storage failed.');
            }

            $source = null;
            $complete = false;

            try {
                $source = @fopen($sourcePath, 'rb');

                if ($source === false) {
                    throw new RuntimeException('Payment method QR storage failed.');
                }

                $written = stream_copy_to_stream($source, $destination);

                if ($written !== $expectedSize || !@fflush($destination)) {
                    throw new RuntimeException('Payment method QR storage failed.');
                }

                if (DIRECTORY_SEPARATOR !== '\\' && !@chmod($destinationPath, 0644)) {
                    throw new RuntimeException('Payment method QR storage failed.');
                }

                $complete = true;

                return self::PATH_PREFIX . $filename;
            } catch (Throwable) {
                throw new RuntimeException('Payment method QR storage failed.');
            } finally {
                if (is_resource($source)) {
                    @fclose($source);
                }

                @fclose($destination);

                if (!$complete && @is_file($destinationPath) && !@is_link($destinationPath)) {
                    @unlink($destinationPath);
                }
            }
        }

        throw new RuntimeException('Payment method QR storage failed.');
    }

    public function remove(string $relativePath): void
    {
        if (preg_match('/^payment-methods\/([a-f0-9]{64}\.(?:jpg|png|webp))$/D', $relativePath, $matches) !== 1) {
            throw new RuntimeException('Payment method QR removal failed.');
        }

        $path = $this->root . DIRECTORY_SEPARATOR . $matches[1];
        clearstatcache(true, $path);

        if (@is_link($path)) {
            throw new RuntimeException('Payment method QR removal failed.');
        }

        if (!@file_exists($path)) {
            return;
        }

        if (!@is_file($path) || !@unlink($path)) {
            throw new RuntimeException('Payment method QR removal failed.');
        }
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1;
    }

    private static function pathsEqual(string $left, string $right): bool
    {
        $normalize = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');
        $left = $normalize($left);
        $right = $normalize($right);

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($left, $right) === 0
            : $left === $right;
    }
}
