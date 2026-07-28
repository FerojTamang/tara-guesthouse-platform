<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentMethodException;
use Closure;
use Throwable;

/**
 * Lazily constructs the database-backed payment-method management service.
 */
final class PaymentMethodManagementServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?PaymentMethodManagementService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PaymentMethodManagementService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PaymentMethodManagementService) {
                throw PaymentMethodException::failure();
            }

            return $this->service = $service;
        } catch (PaymentMethodException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw PaymentMethodException::failure($throwable);
        }
    }
}
