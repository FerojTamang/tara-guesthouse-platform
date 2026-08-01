<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use Closure;
use Throwable;

/**
 * Lazily constructs the public activity catalogue service.
 */
final class PublicActivityServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?PublicActivityService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicActivityService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PublicActivityService) {
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
