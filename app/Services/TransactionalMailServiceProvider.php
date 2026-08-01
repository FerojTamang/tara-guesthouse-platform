<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailException;
use Closure;
use Throwable;

/** Lazily constructs the configured transactional-mail service. */
final class TransactionalMailServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?TransactionalMailService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): TransactionalMailService
    {
        if ($this->service instanceof TransactionalMailService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof TransactionalMailService) {
                throw MailException::failure();
            }

            return $this->service = $service;
        } catch (MailException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw MailException::failure();
        }
    }
}
