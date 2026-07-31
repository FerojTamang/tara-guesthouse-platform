<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use Closure;
use Throwable;

/** Lazily constructs the Administrator CMS management service. */
final class CmsManagementServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?AdministratorCmsService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorCmsService
    {
        if ($this->service instanceof AdministratorCmsService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof AdministratorCmsService) {
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
