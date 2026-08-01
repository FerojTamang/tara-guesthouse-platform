<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use Closure;
use Throwable;

/** Lazily constructs the protected Administrator review moderation service. */
final class AdministratorReviewServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?AdministratorReviewService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): AdministratorReviewService
    {
        if ($this->service instanceof AdministratorReviewService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();
        } catch (Throwable) {
            throw ReviewException::failure();
        }

        if (!$service instanceof AdministratorReviewService) {
            throw ReviewException::failure();
        }

        return $this->service = $service;
    }
}
