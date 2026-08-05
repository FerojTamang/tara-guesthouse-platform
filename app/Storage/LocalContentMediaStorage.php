<?php

declare(strict_types=1);

namespace App\Storage;

use App\Exceptions\ContentMediaException;
use App\Requests\ContentMediaUploadRequest;
use Closure;
use Throwable;

/** Stores validated content images in the public content-media directory. */
final class LocalContentMediaStorage implements ContentMediaStorage
{
    private const MAXIMUM_COLLISION_ATTEMPTS = 8;
    private readonly string $root;
    /** @var array{device: int, inode: int} */
    private readonly array $rootIdentity;
    private readonly Closure $randomBytes;
    private readonly Closure $writer;
    private readonly Closure $remover;

    /**
     * @param null|Closure(int): string $randomBytes
     * @param null|Closure(resource, resource, int): int $writer
     * @param null|Closure(string): bool $remover
     */
    public function __construct(
        string $root,
        ?Closure $randomBytes = null,
        ?Closure $writer = null,
        private readonly int $maximumCollisionAttempts = self::MAXIMUM_COLLISION_ATTEMPTS,
        ?Closure $remover = null,
    ) {
        if ($maximumCollisionAttempts <= 0) {
            throw ContentMediaException::failure();
        }

        [$this->root, $this->rootIdentity] = $this->resolveRoot($root);
        $this->randomBytes = $randomBytes ?? static fn (int $length): string => random_bytes($length);
        $this->writer = $writer ?? self::writeBytes(...);
        $this->remover = $remover ?? static fn (string $path): bool => @unlink($path);
    }

    public function store(ContentMediaUploadRequest $request): StoredContentMedia
    {
        $sourcePath = $request->file()->temporaryPath();
        $expectedSize = $request->file()->size();

        if (
            $expectedSize <= 0
            || !@is_file($sourcePath)
            || @is_link($sourcePath)
            || !@is_readable($sourcePath)
        ) {
            throw ContentMediaException::failure();
        }

        clearstatcache(true, $sourcePath);
        if (@filesize($sourcePath) !== $expectedSize) {
            throw ContentMediaException::failure();
        }

        for ($attempt = 0; $attempt < $this->maximumCollisionAttempts; $attempt++) {
            $filename = $this->generateFilename($request->extension());
            $this->revalidateRoot();
            $destinationPath = $this->destinationPath($filename);
            $destination = @fopen($destinationPath, 'x+b');

            if ($destination === false) {
                if (@is_file($destinationPath) || @is_link($destinationPath)) {
                    continue;
                }
                throw ContentMediaException::failure();
            }

            $source = null;
            $complete = false;

            try {
                clearstatcache(true, $sourcePath);
                if (
                    !@is_file($sourcePath)
                    || @is_link($sourcePath)
                    || !@is_readable($sourcePath)
                    || @filesize($sourcePath) !== $expectedSize
                ) {
                    throw ContentMediaException::failure();
                }

                $source = @fopen($sourcePath, 'rb');
                if ($source === false) {
                    throw ContentMediaException::failure();
                }

                $written = ($this->writer)($source, $destination, $expectedSize);
                if ($written !== $expectedSize || !@fflush($destination)) {
                    throw ContentMediaException::failure();
                }

                $stats = @fstat($destination);
                if (!is_array($stats) || ($stats['size'] ?? null) !== $expectedSize || !@rewind($destination)) {
                    throw ContentMediaException::failure();
                }

                $hash = @hash_init('sha256');
                $hashedBytes = @hash_update_stream($hash, $destination);
                $digest = @hash_final($hash);

                if (
                    $hashedBytes !== $expectedSize
                    || !is_string($digest)
                    || !hash_equals($request->sha256(), $digest)
                ) {
                    throw ContentMediaException::failure();
                }

                $complete = true;

                return new StoredContentMedia(
                    'uploads/content/' . $filename,
                    $filename,
                    $request->extension(),
                    $request->detectedMediaType(),
                    $expectedSize,
                    $request->width(),
                    $request->height(),
                    $request->sha256(),
                );
            } catch (ContentMediaException $exception) {
                throw $exception;
            } catch (Throwable $failure) {
                throw ContentMediaException::failure($failure);
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

        throw ContentMediaException::failure();
    }

    public function remove(string $relativePath): void
    {
        if (preg_match('/^uploads\/content\/([a-f0-9]{64}\.(?:jpg|png|webp))$/D', $relativePath, $matches) !== 1) {
            throw ContentMediaException::failure();
        }

        try {
            $this->revalidateRoot();
            $path = $this->destinationPath($matches[1]);
            clearstatcache(true, $path);

            if (@is_link($path)) {
                throw ContentMediaException::failure();
            }
            if (!@file_exists($path)) {
                return;
            }
            if (!@is_file($path) || !(bool) ($this->remover)($path)) {
                throw ContentMediaException::failure();
            }
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
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
            || !@is_dir($root)
            || @is_link($root)
            || !@is_writable($root)
        ) {
            throw ContentMediaException::failure();
        }

        $resolved = @realpath($root);
        $identity = $this->directoryIdentity($resolved);
        if ($resolved === false || !$this->pathsEqual($root, $resolved) || $identity === null) {
            throw ContentMediaException::failure();
        }

        return [rtrim($resolved, '\\/'), $identity];
    }

    private function revalidateRoot(): void
    {
        clearstatcache(true, $this->root);
        $resolved = @realpath($this->root);
        $identity = $this->directoryIdentity($resolved);
        if (
            !@is_dir($this->root)
            || @is_link($this->root)
            || !@is_writable($this->root)
            || $resolved === false
            || !$this->pathsEqual($this->root, $resolved)
            || $identity === null
            || $identity !== $this->rootIdentity
        ) {
            throw ContentMediaException::failure();
        }
    }

    private function generateFilename(string $extension): string
    {
        try {
            $bytes = ($this->randomBytes)(32);
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }

        if (!is_string($bytes) || strlen($bytes) !== 32) {
            throw ContentMediaException::failure();
        }

        return bin2hex($bytes) . '.' . $extension;
    }

    private function destinationPath(string $filename): string
    {
        if (preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) !== 1) {
            throw ContentMediaException::failure();
        }

        return $this->root . DIRECTORY_SEPARATOR . $filename;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1;
    }

    private function pathsEqual(string $left, string $right): bool
    {
        $left = rtrim(str_replace('\\', '/', $left), '/');
        $right = rtrim(str_replace('\\', '/', $right), '/');
        return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($left, $right) === 0 : $left === $right;
    }

    /** @return null|array{device: int, inode: int} */
    private function directoryIdentity(string|false $path): ?array
    {
        if (!is_string($path)) {
            return null;
        }
        clearstatcache(true, $path);
        $stats = @stat($path);
        if (!is_array($stats) || !is_int($stats['dev'] ?? null) || !is_int($stats['ino'] ?? null)) {
            return null;
        }
        return ['device' => $stats['dev'], 'inode' => $stats['ino']];
    }

    private function removePartialDestination(string $path): void
    {
        try {
            $this->revalidateRoot();
            if (@is_file($path) || @is_link($path)) {
                ($this->remover)($path);
            }
        } catch (Throwable) {
            // Cleanup must not replace the original storage failure.
        }
    }

    /** @param resource $source @param resource $destination */
    private static function writeBytes($source, $destination, int $expectedSize): int
    {
        $written = 0;
        while ($written < $expectedSize && !feof($source)) {
            $chunk = fread($source, min(8192, $expectedSize - $written));
            if (!is_string($chunk) || $chunk === '') {
                break;
            }
            $length = strlen($chunk);
            $offset = 0;
            while ($offset < $length) {
                $count = fwrite($destination, substr($chunk, $offset));
                if (!is_int($count) || $count <= 0) {
                    return $written;
                }
                $offset += $count;
                $written += $count;
            }
        }
        return $written;
    }
}
