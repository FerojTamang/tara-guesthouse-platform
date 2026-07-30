<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use Closure;
use Throwable;

/**
 * Lazily constructs the shared activity catalogue service.
 */
final class ActivityCatalogServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?ActivityCatalogService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): ActivityCatalogService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof ActivityCatalogService) {
                throw ActivityException::failure();
            }

            return $this->service = $service;
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ActivityException::failure($throwable);
        }
    }
}
