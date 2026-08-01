<?php

declare(strict_types=1);

namespace App\Storage;

use App\Exceptions\GalleryImageException;
use Closure;
use Throwable;

/**
 * Defers environment-backed gallery reader resolution until image delivery.
 */
final class LazyGalleryImageReader implements GalleryImageReader
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?GalleryImageReader $reader = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function read(string $relativePath): ?RetrievedGalleryImage
    {
        return $this->reader()->read($relativePath);
    }

    private function reader(): GalleryImageReader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        try {
            $reader = ($this->factory)();

            if (!$reader instanceof GalleryImageReader) {
                throw GalleryImageException::system();
            }

            return $this->reader = $reader;
        } catch (GalleryImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryImageException::system($failure);
        }
    }
}
