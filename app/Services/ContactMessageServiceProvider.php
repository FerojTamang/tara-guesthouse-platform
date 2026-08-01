<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContactException;
use Closure;
use Throwable;

/** Lazily constructs the contact workflow. */
final class ContactMessageServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?ContactMessageService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): ContactMessageService
    {
        if ($this->service instanceof ContactMessageService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof ContactMessageService) {
                throw ContactException::failure();
            }

            return $this->service = $service;
        } catch (ContactException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContactException::failure($failure);
        }
    }
}
