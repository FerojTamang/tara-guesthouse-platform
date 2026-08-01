<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/** Represents the public review-submission rate-limit decision. */
final readonly class ReviewRateLimitState
{
    public function __construct(
        private bool $blocked,
        private int $remainingAttempts,
        private int $retryAfterSeconds,
    ) {
        if (
            $remainingAttempts < 0
            || $retryAfterSeconds < 0
            || ($blocked && ($remainingAttempts !== 0 || $retryAfterSeconds <= 0))
            || (!$blocked && $retryAfterSeconds !== 0)
        ) {
            throw new InvalidArgumentException('Review rate-limit state is invalid.');
        }
    }

    public function isBlocked(): bool { return $this->blocked; }
    public function remainingAttempts(): int { return $this->remainingAttempts; }
    public function retryAfterSeconds(): int { return $this->retryAfterSeconds; }
}
