<?php

declare(strict_types=1);

namespace App\Authentication;

use InvalidArgumentException;

/**
 * Describes the current failed-login allowance for one remote-address bucket.
 */
final readonly class LoginRateLimitState
{
    public function __construct(
        private bool $blocked,
        private int $remainingAttempts,
        private int $retryAfterSeconds,
    ) {
        if (
            $remainingAttempts < 0
            || $retryAfterSeconds < 0
            || ($blocked && $retryAfterSeconds === 0)
            || (!$blocked && $retryAfterSeconds !== 0)
        ) {
            throw new InvalidArgumentException('Login rate-limit state is invalid.');
        }
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function remainingAttempts(): int
    {
        return $this->remainingAttempts;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
