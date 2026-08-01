<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one immutable single-use review invitation. */
final readonly class ReviewInvitation
{
    public function __construct(
        private int $id,
        private int $bookingId,
        private string $tokenHash,
        private DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $usedAt,
        private DateTimeImmutable $createdAt,
    ) {
        if (
            $id <= 0
            || $bookingId <= 0
            || preg_match('/^[a-f0-9]{64}$/D', $tokenHash) !== 1
            || $expiresAt->getOffset() !== 0
            || ($usedAt instanceof DateTimeImmutable && $usedAt->getOffset() !== 0)
            || $createdAt->getOffset() !== 0
            || $expiresAt <= $createdAt
            || ($usedAt instanceof DateTimeImmutable && ($usedAt < $createdAt || $usedAt >= $expiresAt))
        ) {
            throw new InvalidArgumentException('Review invitation data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function bookingId(): int { return $this->bookingId; }
    public function tokenHash(): string { return $this->tokenHash; }
    public function expiresAt(): DateTimeImmutable { return $this->expiresAt; }
    public function usedAt(): ?DateTimeImmutable { return $this->usedAt; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function isUsed(): bool { return $this->usedAt instanceof DateTimeImmutable; }
    public function isExpiredAt(DateTimeImmutable $moment): bool
    {
        if ($moment->getOffset() !== 0) {
            throw new InvalidArgumentException('Review invitation comparison time is invalid.');
        }

        return $moment >= $this->expiresAt;
    }
}
