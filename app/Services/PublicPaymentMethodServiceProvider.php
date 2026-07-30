<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PublicPaymentMethodException;
use Closure;
use Throwable;

/**
 * Lazily constructs the public payment-method service.
 */
final class PublicPaymentMethodServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?PublicPaymentMethodService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicPaymentMethodService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PublicPaymentMethodService) {
                throw PublicPaymentMethodException::failure();
            }

            return $this->service = $service;
        } catch (PublicPaymentMethodException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw PublicPaymentMethodException::failure($throwable);
        }
    }
}
