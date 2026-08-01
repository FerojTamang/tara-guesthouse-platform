<?php

declare(strict_types=1);

namespace App\Storage;

use App\Exceptions\GalleryImageException;
use App\Requests\GalleryImageUploadRequest;
use Closure;
use Throwable;

/**
 * Defers environment-backed gallery storage resolution until a file operation occurs.
 */
final class LazyGalleryImageStorage implements GalleryImageStorage
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?GalleryImageStorage $storage = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function store(GalleryImageUploadRequest $request): StoredGalleryImage
    {
        return $this->storage()->store($request);
    }

    public function remove(string $relativePath): void
    {
        $this->storage()->remove($relativePath);
    }

    private function storage(): GalleryImageStorage
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        try {
            $storage = ($this->factory)();

            if (!$storage instanceof GalleryImageStorage) {
                throw GalleryImageException::system();
            }

            return $this->storage = $storage;
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::system($failure);
        }
    }
}
