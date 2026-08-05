<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use Closure;
use Throwable;

/** Lazily constructs the public navigation projection service. */
final class PublicNavigationServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?PublicNavigationService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicNavigationService
    {
        if ($this->service instanceof PublicNavigationService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();
            if (!$service instanceof PublicNavigationService) {
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
