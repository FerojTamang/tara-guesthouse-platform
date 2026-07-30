<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Environment;
use App\Exceptions\GalleryImageException;
use App\Storage\GalleryImageReader;
use App\Storage\GalleryImageStorage;
use App\Storage\LocalGalleryImageReader;
use App\Storage\LocalGalleryImageStorage;
use Throwable;

/**
 * Resolves the explicitly approved gallery upload policy and private storage.
 */
final class GalleryImageStorageProvider
{
    private const SUPPORTED_FORMATS = [
        'jpeg' => ['image/jpeg' => ['jpg', 'jpeg']],
        'png' => ['image/png' => ['png']],
        'webp' => ['image/webp' => ['webp']],
    ];

    private ?GalleryImageStorage $storage = null;
    private ?GalleryImageReader $reader = null;

    /** @var null|array<string, mixed> */
    private ?array $uploadConfiguration = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Environment $environment = null,
    ) {
    }

    public function storage(): GalleryImageStorage
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        try {
            $configuration = $this->uploadConfiguration();
            $root = $this->absoluteRoot($configuration['filesystem_root']);
            $public = $this->absoluteRoot('public');

            if ($this->isInside($root, $public)) {
                throw GalleryImageException::system();
            }

            return $this->storage = new LocalGalleryImageStorage($root);
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::system($failure);
        }
    }

    public function reader(): GalleryImageReader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        try {
            $configuration = $this->uploadConfiguration();
            $root = $this->absoluteRoot($configuration['filesystem_root']);
            $public = $this->absoluteRoot('public');

            if ($this->isInside($root, $public)) {
                throw GalleryImageException::system();
            }

            return $this->reader = new LocalGalleryImageReader($root, $configuration);
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::system($failure);
        }
    }

    /** @return array<string, mixed> */
    public function uploadConfiguration(): array
    {
        if ($this->uploadConfiguration !== null) {
            return $this->uploadConfiguration;
        }

        try {
            $descriptor = $this->descriptor();
            $environment = $this->environment ?? Environment::load($this->basePath);
            $formats = $this->formats($environment->literalString(
                $descriptor['allowed_formats_environment_key'],
                '',
            ));
            $maximumBytes = $this->positiveInteger(
                $environment,
                $descriptor['maximum_file_size_environment_key'],
            );
            $minimumWidth = $this->positiveInteger(
                $environment,
                $descriptor['minimum_width_environment_key'],
            );
            $minimumHeight = $this->positiveInteger(
                $environment,
                $descriptor['minimum_height_environment_key'],
            );
            $maximumWidth = $this->positiveInteger(
                $environment,
                $descriptor['maximum_width_environment_key'],
            );
            $maximumHeight = $this->positiveInteger(
                $environment,
                $descriptor['maximum_height_environment_key'],
            );
            $decodability = $environment->bool(
                $descriptor['require_decodability_environment_key'],
                false,
            );

            if (
                $formats === []
                || $maximumWidth < $minimumWidth
                || $maximumHeight < $minimumHeight
                || $decodability !== true
                || $descriptor['filesystem_root'] !== 'storage/private/gallery'
                || $descriptor['database_path_prefix'] !== 'gallery/'
            ) {
                throw GalleryImageException::system();
            }

            return $this->uploadConfiguration = [
                'allowed_mime_extensions' => $formats,
                'maximum_file_size_bytes' => $maximumBytes,
                'minimum_width_pixels' => $minimumWidth,
                'minimum_height_pixels' => $minimumHeight,
                'maximum_width_pixels' => $maximumWidth,
                'maximum_height_pixels' => $maximumHeight,
                'require_decodability' => true,
                'filesystem_root' => $descriptor['filesystem_root'],
                'database_path_prefix' => $descriptor['database_path_prefix'],
            ];
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::system($failure);
        }
    }

    /** @return array<string, string> */
    private function descriptor(): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php';

        if (!is_file($path)) {
            throw GalleryImageException::system();
        }

        $configuration = require $path;
        $gallery = is_array($configuration) ? ($configuration['gallery_images'] ?? null) : null;
        $keys = [
            'allowed_formats_environment_key',
            'maximum_file_size_environment_key',
            'minimum_width_environment_key',
            'minimum_height_environment_key',
            'maximum_width_environment_key',
            'maximum_height_environment_key',
            'require_decodability_environment_key',
            'filesystem_root',
            'database_path_prefix',
        ];

        if (!is_array($gallery) || array_keys($gallery) !== $keys) {
            throw GalleryImageException::system();
        }

        foreach ($gallery as $value) {
            if (!is_string($value) || trim($value) === '' || str_contains($value, "\0")) {
                throw GalleryImageException::system();
            }
        }

        /** @var array<string, string> $gallery */
        return $gallery;
    }

    /** @return array<string, list<string>> */
    private function formats(string $value): array
    {
        if ($value === '' || $value !== trim($value)) {
            throw GalleryImageException::system();
        }

        $tokens = explode(',', strtolower($value));

        if ($tokens === [] || count($tokens) !== count(array_unique($tokens))) {
            throw GalleryImageException::system();
        }

        $allowed = [];

        foreach ($tokens as $token) {
            if ($token === '' || $token !== trim($token) || !isset(self::SUPPORTED_FORMATS[$token])) {
                throw GalleryImageException::system();
            }

            foreach (self::SUPPORTED_FORMATS[$token] as $mime => $extensions) {
                $allowed[$mime] = $extensions;
            }
        }

        return $allowed;
    }

    private function positiveInteger(Environment $environment, string $key): int
    {
        $value = $environment->int($key);

        if ($value <= 0) {
            throw GalleryImageException::system();
        }

        return $value;
    }

    private function absoluteRoot(string $relative): string
    {
        $base = @realpath($this->basePath);

        if (!is_string($base) || !is_dir($base)) {
            throw GalleryImageException::system();
        }

        $candidate = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $resolved = @realpath($candidate);

        if (!is_string($resolved)) {
            throw GalleryImageException::system();
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
