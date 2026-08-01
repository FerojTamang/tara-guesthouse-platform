<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotificationException;
use Closure;
use Throwable;

/** Lazily constructs the Administrator notification workflow. */
final class AdministratorNotificationServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?AdministratorNotificationService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorNotificationService
    {
        if ($this->service instanceof AdministratorNotificationService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof AdministratorNotificationService) {
                throw NotificationException::failure();
            }

            return $this->service = $service;
        } catch (NotificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw NotificationException::failure();
        }
    }
}
