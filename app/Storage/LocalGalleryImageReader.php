<?php

declare(strict_types=1);

namespace App\Storage;

use App\Exceptions\GalleryImageException;
use finfo;
use Throwable;

/**
 * Revalidates and reads gallery images from one private local directory.
 */
final class LocalGalleryImageReader implements GalleryImageReader
{
    private readonly string $root;

    /** @var array{device: int, inode: int} */
    private readonly array $rootIdentity;

    /** @var array<string, list<string>> */
    private readonly array $allowedMimeExtensions;

    private readonly int $maximumFileSizeBytes;
    private readonly int $minimumWidthPixels;
    private readonly int $minimumHeightPixels;
    private readonly int $maximumWidthPixels;
    private readonly int $maximumHeightPixels;

    /** @param array<string, mixed> $configuration */
    public function __construct(string $root, array $configuration)
    {
        [
            $this->allowedMimeExtensions,
            $this->maximumFileSizeBytes,
            $this->minimumWidthPixels,
            $this->minimumHeightPixels,
            $this->maximumWidthPixels,
            $this->maximumHeightPixels,
        ] = $this->policy($configuration);
        [$this->root, $this->rootIdentity] = $this->resolveRoot($root);
    }

    public function read(string $relativePath): ?RetrievedGalleryImage
    {
        if (
            preg_match('/^gallery\/([a-f0-9]{64}\.(jpg|png|webp))$/D', $relativePath, $matches) !== 1
        ) {
            throw GalleryImageException::storage();
        }

        $filename = $matches[1];
        $extension = $matches[2];
        $expectedMediaType = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        };

        if (
            !isset($this->allowedMimeExtensions[$expectedMediaType])
            || !in_array($extension, $this->allowedMimeExtensions[$expectedMediaType], true)
        ) {
            throw GalleryImageException::storage();
        }

        try {
            $this->revalidateRoot();
            $path = $this->root . DIRECTORY_SEPARATOR . $filename;
            clearstatcache(true, $path);

            if (@is_link($path)) {
                throw GalleryImageException::storage();
            }

            if (!@file_exists($path)) {
                return null;
            }

            $pathIdentity = $this->fileIdentity($path);

            if (
                !@is_file($path)
                || !@is_readable($path)
                || $pathIdentity === null
                || $pathIdentity['size'] <= 0
                || $pathIdentity['size'] > $this->maximumFileSizeBytes
            ) {
                throw GalleryImageException::storage();
            }

            $this->revalidateRoot();
            $handle = @fopen($path, 'rb');

            if ($handle === false) {
                throw GalleryImageException::storage();
            }

            try {
                $streamIdentityBefore = $this->streamIdentity(@fstat($handle));
                $bytes = @stream_get_contents($handle, $this->maximumFileSizeBytes + 1);
                $streamIdentityAfter = $this->streamIdentity(@fstat($handle));

                if (
                    !is_string($bytes)
                    || strlen($bytes) !== $pathIdentity['size']
                    || $streamIdentityBefore !== $pathIdentity
                    || $streamIdentityAfter !== $pathIdentity
                ) {
                    throw GalleryImageException::storage();
                }
            } finally {
                @fclose($handle);
            }

            $this->revalidateRoot();
            clearstatcache(true, $path);

            if (@is_link($path) || $this->fileIdentity($path) !== $pathIdentity) {
                throw GalleryImageException::storage();
            }

            $detectedMediaType = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $image = @getimagesizefromstring($bytes);

            if (
                $detectedMediaType !== $expectedMediaType
                || !is_array($image)
                || !isset($image[0], $image[1], $image['mime'])
                || !is_int($image[0])
                || !is_int($image[1])
                || $image['mime'] !== $expectedMediaType
                || $image[0] < $this->minimumWidthPixels
                || $image[1] < $this->minimumHeightPixels
                || $image[0] > $this->maximumWidthPixels
                || $image[1] > $this->maximumHeightPixels
            ) {
                throw GalleryImageException::storage();
            }

            return new RetrievedGalleryImage(
                $bytes,
                $extension,
                $expectedMediaType,
                $image[0],
                $image[1],
            );
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::storage($failure);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{array<string, list<string>>, int, int, int, int, int}
     */
    private function policy(array $configuration): array
    {
        $allowed = $configuration['allowed_mime_extensions'] ?? null;
        $maximumBytes = $configuration['maximum_file_size_bytes'] ?? null;
        $minimumWidth = $configuration['minimum_width_pixels'] ?? null;
        $minimumHeight = $configuration['minimum_height_pixels'] ?? null;
        $maximumWidth = $configuration['maximum_width_pixels'] ?? null;
        $maximumHeight = $configuration['maximum_height_pixels'] ?? null;
        $decodability = $configuration['require_decodability'] ?? null;
        $root = $configuration['filesystem_root'] ?? null;
        $prefix = $configuration['database_path_prefix'] ?? null;
        $supported = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        if (
            !is_array($allowed)
            || $allowed === []
            || !is_int($maximumBytes)
            || !is_int($minimumWidth)
            || !is_int($minimumHeight)
            || !is_int($maximumWidth)
            || !is_int($maximumHeight)
            || $maximumBytes <= 0
            || $minimumWidth <= 0
            || $minimumHeight <= 0
            || $maximumWidth < $minimumWidth
            || $maximumHeight < $minimumHeight
            || $decodability !== true
            || $root !== 'storage/private/gallery'
            || $prefix !== 'gallery/'
        ) {
            throw GalleryImageException::system();
        }

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
            $allowed,
            $maximumBytes,
            $minimumWidth,
            $minimumHeight,
            $maximumWidth,
            $maximumHeight,
        ];
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
            || !@is_readable($root)
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
            || !@is_readable($this->root)
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

    /** @return null|array{device: int, inode: int, size: int} */
    private function fileIdentity(string $path): ?array
    {
        clearstatcache(true, $path);

        return $this->regularFileIdentity(@lstat($path));
    }

    /**
     * @param array<string|int, mixed>|false $stats
     * @return null|array{device: int, inode: int, size: int}
     */
    private function streamIdentity(array|false $stats): ?array
    {
        return $this->regularFileIdentity($stats);
    }

    /**
     * @param array<string|int, mixed>|false $stats
     * @return null|array{device: int, inode: int, size: int}
     */
    private function regularFileIdentity(array|false $stats): ?array
    {
        if (
            !is_array($stats)
            || !isset($stats['dev'], $stats['ino'], $stats['size'], $stats['mode'])
            || !is_int($stats['dev'])
            || !is_int($stats['ino'])
            || !is_int($stats['size'])
            || !is_int($stats['mode'])
            || ($stats['mode'] & 0170000) !== 0100000
        ) {
            return null;
        }

        return [
            'device' => $stats['dev'],
            'inode' => $stats['ino'],
            'size' => $stats['size'],
        ];
    }
}
