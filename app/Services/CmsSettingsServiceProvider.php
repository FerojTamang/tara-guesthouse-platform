<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use Closure;
use Throwable;

/** Defers the approved setting-definition gate until settings are actually accessed. */
final class CmsSettingsServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?CmsSettingsService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): CmsSettingsService
    {
        if ($this->service instanceof CmsSettingsService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof CmsSettingsService) {
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
