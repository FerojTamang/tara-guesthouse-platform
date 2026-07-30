<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuestPaymentException;
use Closure;
use Throwable;

/**
 * Lazily constructs the public Guest payment-submission service.
 */
final class GuestPaymentSubmissionServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?GuestPaymentSubmitter $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): GuestPaymentSubmitter
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof GuestPaymentSubmitter) {
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
