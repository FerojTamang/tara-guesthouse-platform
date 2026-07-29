<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuestPaymentException;
use Closure;
use Throwable;

/**
 * Lazily constructs the Guest payment-page read service.
 */
final class GuestPaymentPageServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?GuestPaymentPageService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): GuestPaymentPageService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof GuestPaymentPageService) {
                throw GuestPaymentException::failure();
            }

            return $this->service = $service;
        } catch (GuestPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }
}
