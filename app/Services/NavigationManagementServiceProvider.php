<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use Closure;
use Throwable;

/** Lazily constructs the Administrator navigation management service. */
final class NavigationManagementServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?AdministratorNavigationService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorNavigationService
    {
        if ($this->service instanceof AdministratorNavigationService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();
            if (!$service instanceof AdministratorNavigationService) {
                throw CmsException::failure();
            }

            return $this->service = $service;
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
