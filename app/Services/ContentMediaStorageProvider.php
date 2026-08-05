<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContentMediaException;
use App\Storage\ContentMediaStorage;
use App\Storage\LocalContentMediaStorage;
use Throwable;

/** Resolves the fixed safe upload policy and public content-media storage root. */
final class ContentMediaStorageProvider
{
    private ?ContentMediaStorage $storage = null;
    /** @var null|array<string, mixed> */
    private ?array $configuration = null;

    public function __construct(private readonly string $basePath)
    {
    }

    public function storage(): ContentMediaStorage
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        try {
            $configuration = $this->uploadConfiguration();
            $root = $this->absoluteRoot($configuration['filesystem_root']);
            $public = $this->absoluteRoot('public');

            if (!$this->isInside($root, $public)) {
                throw ContentMediaException::failure();
            }

            return $this->storage = new LocalContentMediaStorage($root);
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    /** @return array<string, mixed> */
    public function uploadConfiguration(): array
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }

        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php';
        if (!is_file($path)) {
            throw ContentMediaException::failure();
        }

        $all = require $path;
        $configuration = is_array($all) ? ($all['content_media'] ?? null) : null;
        $expectedKeys = [
            'allowed_mime_extensions',
            'maximum_file_size_bytes',
            'minimum_width_pixels',
            'minimum_height_pixels',
            'maximum_width_pixels',
            'maximum_height_pixels',
            'require_decodability',
            'filesystem_root',
            'database_path_prefix',
        ];

        if (!is_array($configuration) || array_keys($configuration) !== $expectedKeys) {
            throw ContentMediaException::failure();
        }

        if (
            $configuration['allowed_mime_extensions'] !== [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
            ]
            || !is_int($configuration['maximum_file_size_bytes'])
            || !is_int($configuration['minimum_width_pixels'])
            || !is_int($configuration['minimum_height_pixels'])
            || !is_int($configuration['maximum_width_pixels'])
            || !is_int($configuration['maximum_height_pixels'])
            || $configuration['require_decodability'] !== true
            || $configuration['maximum_file_size_bytes'] <= 0
            || $configuration['minimum_width_pixels'] <= 0
            || $configuration['minimum_height_pixels'] <= 0
            || $configuration['maximum_width_pixels'] < $configuration['minimum_width_pixels']
            || $configuration['maximum_height_pixels'] < $configuration['minimum_height_pixels']
            || $configuration['filesystem_root'] !== 'public/uploads/content'
            || $configuration['database_path_prefix'] !== 'uploads/content/'
        ) {
            throw ContentMediaException::failure();
        }

        return $this->configuration = $configuration;
    }

    private function absoluteRoot(string $relative): string
    {
        $base = @realpath($this->basePath);
        if (!is_string($base) || !is_dir($base)) {
            throw ContentMediaException::failure();
        }
        $resolved = @realpath($base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
        if (!is_string($resolved) || !is_dir($resolved)) {
            throw ContentMediaException::failure();
        }
        return rtrim($resolved, '\\/');
    }

    private function isInside(string $candidate, string $parent): bool
    {
        $candidate = str_replace('\\', '/', $candidate);
        $parent = rtrim(str_replace('\\', '/', $parent), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $parent = strtolower($parent);
        }
        return $candidate === $parent || str_starts_with($candidate, $parent . '/');
    }
}
