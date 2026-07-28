<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use Closure;
use Throwable;

/**
 * Lazily constructs the database-backed Administrator transition service.
 */
final class AdministratorBookingStatusServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?AdministratorBookingStatusService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorBookingStatusService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof AdministratorBookingStatusService) {
                throw BookingException::failure();
            }

            return $this->service = $service;
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
