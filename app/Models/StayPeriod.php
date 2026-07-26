<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one half-open hotel stay period: check-in is inclusive and check-out is exclusive.
 */
final readonly class StayPeriod
{
    public function __construct(
        private DateTimeImmutable $checkIn,
        private DateTimeImmutable $checkOut,
    ) {
        if (
            !$this->isStoredDate($checkIn)
            || !$this->isStoredDate($checkOut)
            || $checkOut <= $checkIn
        ) {
            throw new InvalidArgumentException('Stay period is invalid.');
        }
    }

    public function checkIn(): DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function checkOut(): DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function nights(): int
    {
        return (int) $this->checkIn->diff($this->checkOut)->format('%a');
    }

    public function overlaps(self $other): bool
    {
        return $this->checkIn < $other->checkOut
            && $this->checkOut > $other->checkIn;
    }

    private function isStoredDate(DateTimeImmutable $date): bool
    {
        return $date->getOffset() === 0
            && $date->format('H:i:s.u') === '00:00:00.000000';
    }
}
