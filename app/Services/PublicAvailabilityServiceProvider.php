<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AvailabilityException;
use Closure;
use Throwable;

/**
 * Lazily constructs the public availability workflow without eager HTTP bootstrap PDO access.
 */
final class PublicAvailabilityServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?PublicAvailabilityService $service = null;

    /**
     * @param callable(): mixed $factory
     */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicAvailabilityService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PublicAvailabilityService) {
                throw AvailabilityException::failure();
            }

            return $this->service = $service;
        } catch (AvailabilityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AvailabilityException::failure($failure);
        }
    }
}
