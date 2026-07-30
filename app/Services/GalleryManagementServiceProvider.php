<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalleryException;
use Closure;
use Throwable;

/**
 * Lazily constructs the Administrator gallery-management service.
 */
final class GalleryManagementServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?AdministratorGalleryService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorGalleryService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof AdministratorGalleryService) {
                throw GalleryException::failure();
            }

            return $this->service = $service;
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryException::failure($failure);
        }
    }
}
