<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RoomImageException;
use App\Repositories\RoomImageRepository;
use App\Repositories\RoomRepository;
use App\Storage\LocalRoomImageStorage;
use Closure;
use PDO;
use Throwable;

final class RoomImageServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $connectionProvider;

    private ?RoomImageService $service = null;

    /** @var null|array<string, mixed> */
    private ?array $uploadConfiguration = null;

    public function __construct(callable $connectionProvider, private readonly string $basePath)
    {
        $this->connectionProvider = Closure::fromCallable($connectionProvider);
    }

    public function service(): RoomImageService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $connection = ($this->connectionProvider)();

            if (!$connection instanceof PDO) {
                throw RoomImageException::system();
            }

            $configuration = $this->uploadConfiguration();
            $root = $configuration['filesystem_root'];

            if (!$this->isAbsolutePath($root)) {
                $root = $this->basePath . DIRECTORY_SEPARATOR
                    . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
            }

            $this->service = new RoomImageService(
                new RoomRepository($connection),
                new RoomImageRepository($connection),
                new LocalRoomImageStorage($root),
                new PdoRoomCatalogTransaction($connection),
                $configuration['maximum_images_per_room'],
            );

            return $this->service;
        } catch (RoomImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw RoomImageException::system($failure);
        }
    }

    /** @return array<string, mixed> */
    public function uploadConfiguration(): array
    {
        if ($this->uploadConfiguration !== null) {
            return $this->uploadConfiguration;
        }

        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php';

        if (!is_file($path)) {
            throw RoomImageException::system();
        }

        $configuration = require $path;
        $roomImages = is_array($configuration) ? ($configuration['room_images'] ?? null) : null;
        $maximum = is_array($roomImages) ? ($roomImages['maximum_images_per_room'] ?? null) : null;
        $root = is_array($roomImages) ? ($roomImages['filesystem_root'] ?? null) : null;
        $allowed = is_array($roomImages) ? ($roomImages['allowed_mime_extensions'] ?? null) : null;
        $maximumSize = is_array($roomImages) ? ($roomImages['maximum_file_size_bytes'] ?? null) : null;
        $minimumWidth = is_array($roomImages) ? ($roomImages['minimum_width_pixels'] ?? null) : null;
        $minimumHeight = is_array($roomImages) ? ($roomImages['minimum_height_pixels'] ?? null) : null;
        $maximumWidth = is_array($roomImages) ? ($roomImages['maximum_width_pixels'] ?? null) : null;
        $maximumHeight = is_array($roomImages) ? ($roomImages['maximum_height_pixels'] ?? null) : null;
        $prefix = is_array($roomImages) ? ($roomImages['database_path_prefix'] ?? null) : null;

        if (
            !is_array($roomImages)
            || !is_array($allowed)
            || $allowed === []
            || !is_int($maximumSize)
            || $maximumSize <= 0
            || !is_int($minimumWidth)
            || $minimumWidth <= 0
            || !is_int($minimumHeight)
            || $minimumHeight <= 0
            || !is_int($maximumWidth)
            || $maximumWidth < $minimumWidth
            || !is_int($maximumHeight)
            || $maximumHeight < $minimumHeight
            || !is_int($maximum)
            || $maximum <= 0
            || !is_string($root)
            || trim($root) === ''
            || str_contains($root, "\0")
            || $prefix !== 'uploads/rooms/'
        ) {
            throw RoomImageException::system();
        }

        foreach ($allowed as $mime => $extensions) {
            if (!is_string($mime) || !is_array($extensions) || $extensions === []) {
                throw RoomImageException::system();
            }

            foreach ($extensions as $extension) {
                if (!is_string($extension) || preg_match('/^[a-z0-9]+$/D', $extension) !== 1) {
                    throw RoomImageException::system();
                }
            }
        }

        $this->uploadConfiguration = $roomImages;

        return $this->uploadConfiguration;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/D', $path) === 1;
    }
}
