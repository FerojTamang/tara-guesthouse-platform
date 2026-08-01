<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalleryException;
use Closure;
use Throwable;

/**
 * Lazily constructs the public gallery service.
 */
final class PublicGalleryServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?PublicGalleryService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicGalleryService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PublicGalleryService) {
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
