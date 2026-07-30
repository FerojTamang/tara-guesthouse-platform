<?php

declare(strict_types=1);

namespace App\Storage;

use App\Exceptions\GalleryImageException;
use App\Requests\GalleryImageUploadRequest;
use Closure;
use Throwable;

/**
 * Stores validated gallery images in one private, non-executable directory.
 */
final class LocalGalleryImageStorage implements GalleryImageStorage
{
    private const DATABASE_PATH_PREFIX = 'gallery/';
    private const MAXIMUM_COLLISION_ATTEMPTS = 8;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private readonly string $root;
    /** @var array{device: int, inode: int} */
    private readonly array $rootIdentity;
    private readonly Closure $randomBytes;
    private readonly Closure $writer;
    private readonly Closure $fileSize;
    private readonly Closure $remover;

    /**
     * Optional callbacks provide deterministic failure seams for tests.
     *
     * @param null|Closure(int): string $randomBytes
     * @param null|Closure(resource, resource, int): int $writer
     * @param null|Closure(string): (int|false) $fileSize
     * @param null|Closure(string): bool $remover
     */
    public function __construct(
        string $root,
        ?Closure $randomBytes = null,
        ?Closure $writer = null,
        private readonly int $maximumCollisionAttempts = self::MAXIMUM_COLLISION_ATTEMPTS,
        ?Closure $fileSize = null,
        ?Closure $remover = null,
    ) {
        if ($maximumCollisionAttempts <= 0) {
            throw GalleryImageException::system();
        }

        [$this->root, $this->rootIdentity] = $this->resolveRoot($root);
        $this->randomBytes = $randomBytes ?? static fn (int $length): string => random_bytes($length);
        $this->writer = $writer ?? self::writeBytes(...);
        $this->fileSize = $fileSize ?? static fn (string $path): int|false => @filesize($path);
        $this->remover = $remover ?? static fn (string $path): bool => @unlink($path);
    }

    public function store(GalleryImageUploadRequest $request): StoredGalleryImage
    {
        $mediaType = $request->detectedMediaType();
        $extension = self::MIME_EXTENSIONS[$mediaType] ?? null;

        if ($extension === null) {
            throw GalleryImageException::storage();
        }

        $sourcePath = $request->file()->temporaryPath();
        $expectedSize = $request->file()->size();

        if (
            $expectedSize <= 0
            || !@is_file($sourcePath)
            || @is_link($sourcePath)
            || !@is_readable($sourcePath)
        ) {
            throw GalleryImageException::storage();
        }

        clearstatcache(true, $sourcePath);

        if ($this->readFileSize($sourcePath) !== $expectedSize) {
            throw GalleryImageException::storage();
        }

        for ($attempt = 0; $attempt < $this->maximumCollisionAttempts; $attempt++) {
            $filename = $this->generateFilename($extension);
            $this->revalidateRoot();
            $destinationPath = $this->destinationPath($filename);
            $destination = @fopen($destinationPath, 'x+b');

            if ($destination === false) {
                $this->revalidateRoot();

                if (@is_file($destinationPath) || @is_link($destinationPath)) {
                    continue;
                }

                throw GalleryImageException::storage();
            }

            $source = null;
            $complete = false;

            try {
                clearstatcache(true, $sourcePath);

                if (
                    !@is_file($sourcePath)
                    || @is_link($sourcePath)
                    || !@is_readable($sourcePath)
                    || $this->readFileSize($sourcePath) !== $expectedSize
                ) {
                    throw GalleryImageException::storage();
                }

                $source = @fopen($sourcePath, 'rb');

                if ($source === false) {
                    throw GalleryImageException::storage();
                }

                $written = ($this->writer)($source, $destination, $expectedSize);

                if ($written !== $expectedSize || !@fflush($destination)) {
                    throw GalleryImageException::storage();
                }

                $stats = @fstat($destination);

                if (!is_array($stats) || ($stats['size'] ?? null) !== $expectedSize) {
                    throw GalleryImageException::storage();
                }

                if (!@rewind($destination)) {
                    throw GalleryImageException::storage();
                }

                $hash = @hash_init('sha256');
                $hashedBytes = @hash_update_stream($hash, $destination);
                $destinationDigest = @hash_final($hash);

                if (
                    $hashedBytes !== $expectedSize
                    || !is_string($destinationDigest)
                    || !hash_equals($request->sha256(), $destinationDigest)
                ) {
                    throw GalleryImageException::storage();
                }

                $complete = true;

                return new StoredGalleryImage(
                    self::DATABASE_PATH_PREFIX . $filename,
                    $filename,
                    $extension,
                    $mediaType,
                    $expectedSize,
                    $request->width(),
                    $request->height(),
                );
            } catch (GalleryImageException $exception) {
                throw $exception;
            } catch (Throwable $failure) {
                throw GalleryImageException::storage($failure);
            } finally {
                if (is_resource($source)) {
                    @fclose($source);
                }

                if (is_resource($destination)) {
                    @fclose($destination);
                }

                if (!$complete) {
                    $this->removePartialDestination($destinationPath);
                }
            }
        }

        throw GalleryImageException::storage();
    }

    public function remove(string $relativePath): void
    {
        if (
            preg_match('/^gallery\/([a-f0-9]{64}\.(?:jpg|png|webp))$/D', $relativePath, $matches) !== 1
        ) {
            throw GalleryImageException::storage();
        }

        try {
            $this->revalidateRoot();
            $path = $this->destinationPath($matches[1]);
            clearstatcache(true, $path);

            if (@is_link($path)) {
                throw GalleryImageException::storage();
            }

            if (!@file_exists($path)) {
                return;
            }

            if (!@is_file($path)) {
                throw GalleryImageException::storage();
            }

            $this->revalidateRoot();

            if (!(bool) ($this->remover)($path)) {
                throw GalleryImageException::storage();
            }
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::storage($failure);
        }
    }

    /** @return array{string, array{device: int, inode: int}} */
    private function resolveRoot(string $root): array
    {
        clearstatcache(true, $root);

        if (
            $root === ''
            || str_contains($root, "\0")
            || !$this->isAbsolutePath($root)
            || !@file_exists($root)
            || !@is_dir($root)
            || @is_link($root)
            || !@is_writable($root)
        ) {
            throw GalleryImageException::system();
        }

        $resolved = @realpath($root);
        $identity = $this->directoryIdentity($resolved);

        if ($resolved === false || !$this->pathsEqual($root, $resolved) || $identity === null) {
            throw GalleryImageException::system();
        }

        return [rtrim($resolved, '\\/'), $identity];
    }

    private function revalidateRoot(): void
    {
        clearstatcache(true, $this->root);

        if (
            !@file_exists($this->root)
            || !@is_dir($this->root)
            || @is_link($this->root)
            || !@is_writable($this->root)
        ) {
            throw GalleryImageException::storage();
        }

        $resolved = @realpath($this->root);
        $identity = $this->directoryIdentity($resolved);

        if (
            $resolved === false
            || !$this->pathsEqual($this->root, $resolved)
            || $identity === null
            || $identity !== $this->rootIdentity
        ) {
            throw GalleryImageException::storage();
        }
    }

    private function generateFilename(string $extension): string
    {
        try {
            $bytes = ($this->randomBytes)(32);
        } catch (Throwable $failure) {
            throw GalleryImageException::storage($failure);
        }

        if (!is_string($bytes) || strlen($bytes) !== 32) {
            throw GalleryImageException::storage();
        }

        return bin2hex($bytes) . '.' . $extension;
    }

    private function destinationPath(string $filename): string
    {
        if (preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) !== 1) {
            throw GalleryImageException::storage();
        }

        return $this->root . DIRECTORY_SEPARATOR . $filename;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1;
    }

    private function pathsEqual(string $configured, string $resolved): bool
    {
        $configured = rtrim(str_replace('\\', '/', $configured), '/');
        $resolved = rtrim(str_replace('\\', '/', $resolved), '/');

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($configured, $resolved) === 0
            : $configured === $resolved;
    }

    /** @return null|array{device: int, inode: int} */
    private function directoryIdentity(string|false $path): ?array
    {
        if (!is_string($path)) {
            return null;
        }

        clearstatcache(true, $path);
        $stats = @stat($path);

        if (
            !is_array($stats)
            || !isset($stats['dev'], $stats['ino'])
            || !is_int($stats['dev'])
            || !is_int($stats['ino'])
        ) {
            return null;
        }

        return ['device' => $stats['dev'], 'inode' => $stats['ino']];
    }

    private function readFileSize(string $path): int|false
    {
        try {
            $size = ($this->fileSize)($path);
        } catch (Throwable $failure) {
            throw GalleryImageException::storage($failure);
        }

        return is_int($size) ? $size : false;
    }

    private function removePartialDestination(string $path): void
    {
        try {
            $this->revalidateRoot();
        } catch (Throwable) {
            return;
        }

        if (!@is_file($path) && !@is_link($path)) {
            return;
        }

        try {
            ($this->remover)($path);
        } catch (Throwable) {
            // Cleanup must never disclose or replace the original write failure.
        }
    }

    /**
     * @param resource $source
     * @param resource $destination
     */
    private static function writeBytes($source, $destination, int $expectedSize): int
    {
        $written = 0;

        while ($written < $expectedSize && !@feof($source)) {
            $chunk = @fread($source, min(8192, $expectedSize - $written));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $offset = 0;
            $length = strlen($chunk);

            while ($offset < $length) {
                $result = @fwrite($destination, substr($chunk, $offset));

                if ($result === false || $result === 0) {
                    return $written;
                }

                $offset += $result;
                $written += $result;
            }
        }

        return $written;
    }
}
