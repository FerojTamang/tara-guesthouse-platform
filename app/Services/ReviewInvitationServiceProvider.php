<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use Closure;
use Throwable;

/** Lazily constructs the verified-stay invitation service. */
final class ReviewInvitationServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?ReviewInvitationService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): ReviewInvitationService
    {
        if ($this->service instanceof ReviewInvitationService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof ReviewInvitationService) {
                throw ReviewException::failure();
            }

            return $this->service = $service;
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ReviewException::failure($failure);
        }
    }
}
