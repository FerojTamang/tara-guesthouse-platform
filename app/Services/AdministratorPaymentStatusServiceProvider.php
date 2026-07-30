<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorPaymentException;
use Closure;
use Throwable;

/**
 * Lazily constructs the Administrator payment-review workflow.
 */
final class AdministratorPaymentStatusServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?AdministratorPaymentStatusService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorPaymentStatusService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof AdministratorPaymentStatusService) {
                throw AdministratorPaymentException::failure();
            }

            return $this->service = $service;
        } catch (AdministratorPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AdministratorPaymentException::failure($failure);
        }
    }
}
