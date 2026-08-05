<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContentMediaException;
use Closure;
use Throwable;

/** Lazily constructs the Administrator content-media service. */
final class ContentMediaManagementServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?AdministratorContentMediaService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorContentMediaService
    {
        if ($this->service instanceof AdministratorContentMediaService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();
            if (!$service instanceof AdministratorContentMediaService) {
                throw ContentMediaException::failure();
            }
            return $this->service = $service;
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }
}
