<?php

declare(strict_types=1);

namespace App\Storage;

use App\Core\UploadedFile;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Stores validated room images in one configured local directory.
 */
final class LocalRoomImageStorage implements RoomImageStorage
{
    private const DATABASE_PATH_PREFIX = 'uploads/rooms/';
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
     * The optional callbacks are deterministic test seams; production uses the
     * cryptographic generator and binary stream writer below.
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
            throw new RuntimeException('Room image storage is unavailable.');
        }

        [$this->root, $this->rootIdentity] = $this->resolveRoot($root);
        $this->randomBytes = $randomBytes ?? static fn (int $length): string => random_bytes($length);
        $this->writer = $writer ?? self::writeBytes(...);
        $this->fileSize = $fileSize ?? static fn (string $path): int|false => @filesize($path);
        $this->remover = $remover ?? static fn (string $path): bool => @unlink($path);
    }

    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage
    {
        $extension = self::MIME_EXTENSIONS[$detectedMediaType] ?? null;

        if ($extension === null) {
            throw new RuntimeException('Room image storage failed.');
        }

        $sourcePath = $file->temporaryPath();
        $expectedSize = $file->size();

        if (
            $expectedSize <= 0
            || !@is_file($sourcePath)
            || @is_link($sourcePath)
            || !@is_readable($sourcePath)
        ) {
            throw new RuntimeException('Room image storage failed.');
        }

        clearstatcache(true, $sourcePath);
        $actualSize = $this->readFileSize($sourcePath);

        if (!is_int($actualSize) || $actualSize !== $expectedSize) {
            throw new RuntimeException('Room image storage failed.');
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

                throw new RuntimeException('Room image storage failed.');
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
                    throw new RuntimeException('Room image storage failed.');
                }

                $source = @fopen($sourcePath, 'rb');

                if ($source === false) {
                    throw new RuntimeException('Room image storage failed.');
                }

                $written = ($this->writer)($source, $destination, $expectedSize);

                if ($written !== $expectedSize || !@fflush($destination)) {
                    throw new RuntimeException('Room image storage failed.');
                }

                $destinationStats = @fstat($destination);

                if (!is_array($destinationStats) || ($destinationStats['size'] ?? null) !== $expectedSize) {
                    throw new RuntimeException('Room image storage failed.');
                }

                $complete = true;

                return new StoredRoomImage(
                    self::DATABASE_PATH_PREFIX . $filename,
                    $filename,
                    $extension,
                    $expectedSize,
                );
            } catch (Throwable) {
                throw new RuntimeException('Room image storage failed.');
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

        throw new RuntimeException('Room image storage failed.');
    }

    public function remove(string $relativePath): void
    {
        if (
            preg_match(
                '/^uploads\/rooms\/([a-f0-9]{64}\.(?:jpg|png|webp))$/D',
                $relativePath,
                $matches,
            ) !== 1
        ) {
            throw new RuntimeException('Room image removal failed.');
        }

        try {
            $this->revalidateRoot();
            $destinationPath = $this->destinationPath($matches[1]);
            clearstatcache(true, $destinationPath);

            if (@is_link($destinationPath)) {
                throw new RuntimeException('Room image removal failed.');
            }

            if (!@file_exists($destinationPath)) {
                return;
            }

            if (!@is_file($destinationPath)) {
                throw new RuntimeException('Room image removal failed.');
            }

            $this->revalidateRoot();

            if (!(bool) ($this->remover)($destinationPath)) {
                throw new RuntimeException('Room image removal failed.');
            }
        } catch (Throwable) {
            throw new RuntimeException('Room image removal failed.');
        }
    }

    /**
     * @return array{string, array{device: int, inode: int}}
     */
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
            throw new RuntimeException('Room image storage is unavailable.');
        }

        $resolved = @realpath($root);
        $identity = $this->directoryIdentity($resolved);

        if (
            $resolved === false
            || !$this->pathsEqual($root, $resolved)
            || $identity === null
        ) {
            throw new RuntimeException('Room image storage is unavailable.');
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
            throw new RuntimeException('Room image storage failed.');
        }

        $resolved = @realpath($this->root);
        $identity = $this->directoryIdentity($resolved);

        if (
            $resolved === false
            || !$this->pathsEqual($this->root, $resolved)
            || $identity === null
            || $identity !== $this->rootIdentity
        ) {
            throw new RuntimeException('Room image storage failed.');
        }
    }

    private function generateFilename(string $extension): string
    {
        try {
            $bytes = ($this->randomBytes)(32);
        } catch (Throwable) {
            throw new RuntimeException('Room image storage failed.');
        }

        if (!is_string($bytes) || strlen($bytes) !== 32) {
            throw new RuntimeException('Room image storage failed.');
        }

        return bin2hex($bytes) . '.' . $extension;
    }

    private function destinationPath(string $filename): string
    {
        if (preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) !== 1) {
            throw new RuntimeException('Room image storage failed.');
        }

        return $this->root . DIRECTORY_SEPARATOR . $filename;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/D', $path) === 1;
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
        } catch (Throwable) {
            throw new RuntimeException('Room image storage failed.');
        }

        return is_int($size) ? $size : false;
    }

    private function removePartialDestination(string $destinationPath): void
    {
        try {
            $this->revalidateRoot();
        } catch (Throwable) {
            return;
        }

        if (!@is_file($destinationPath) && !@is_link($destinationPath)) {
            return;
        }

        try {
            ($this->remover)($destinationPath);
        } catch (Throwable) {
            // Cleanup must not replace or disclose the original storage failure.
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
            $chunkLength = strlen($chunk);

            while ($offset < $chunkLength) {
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
