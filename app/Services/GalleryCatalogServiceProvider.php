<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalleryException;
use Closure;
use Throwable;

/**
 * Lazily constructs the shared gallery catalogue service.
 */
final class GalleryCatalogServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?GalleryCatalogService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): GalleryCatalogService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof GalleryCatalogService) {
                throw GalleryException::failure();
            }

            return $this->service = $service;
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw GalleryException::failure($throwable);
        }
    }
}
