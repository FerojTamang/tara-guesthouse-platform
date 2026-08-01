<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use Closure;
use Throwable;

/** Lazily constructs the public verified-stay review service. */
final class PublicReviewServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?PublicReviewService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicReviewService
    {
        if ($this->service instanceof PublicReviewService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PublicReviewService) {
                throw ReviewException::failure();
            }

            return $this->service = $service;
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ReviewException::failure();
        }
    }
}
