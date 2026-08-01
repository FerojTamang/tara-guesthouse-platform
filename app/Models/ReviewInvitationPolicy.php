<?php

declare(strict_types=1);

namespace App\Models;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/** Holds the required environment-approved invitation lifetime. */
final readonly class ReviewInvitationPolicy
{
    public function __construct(private int $lifetimeSeconds)
    {
        if ($lifetimeSeconds <= 0) {
            throw new InvalidArgumentException('Review invitation policy is invalid.');
        }
    }

    public function lifetimeSeconds(): int { return $this->lifetimeSeconds; }

    public function expiresAt(DateTimeImmutable $createdAt): DateTimeImmutable
    {
        if ($createdAt->getOffset() !== 0) {
            throw new InvalidArgumentException('Review invitation creation time is invalid.');
        }

        try {
            $expiresAt = $createdAt->add(new DateInterval('PT' . $this->lifetimeSeconds . 'S'));
        } catch (Throwable $failure) {
            throw new InvalidArgumentException('Review invitation policy is invalid.', previous: $failure);
        }

        if ($expiresAt <= $createdAt || $expiresAt->getOffset() !== 0) {
            throw new InvalidArgumentException('Review invitation policy is invalid.');
        }

        return $expiresAt;
    }
}
