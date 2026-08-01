<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use Closure;
use Throwable;

/**
 * Lazily constructs the Administrator activity-management service.
 */
final class ActivityManagementServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?AdministratorActivityService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorActivityService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof AdministratorActivityService) {
                throw ActivityException::failure();
            }

            return $this->service = $service;
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ActivityException::failure($failure);
        }
    }
}
