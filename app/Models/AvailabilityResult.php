<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable availability calculation result.
 */
final readonly class AvailabilityResult
{
    /**
     * @param list<int> $roomIds
     */
    public function __construct(
        private StayPeriod $stayPeriod,
        private int $adults,
        private int $children,
        private DateTimeImmutable $checkedAt,
        private array $roomIds,
    ) {
        if (
            $adults < 1
            || $children < 0
            || $checkedAt->getOffset() !== 0
            || !array_is_list($roomIds)
            || !$this->containsOnlyUniquePositiveIdentifiers($roomIds)
        ) {
            throw new InvalidArgumentException('Availability result data is invalid.');
        }
    }

    public function stayPeriod(): StayPeriod
    {
        return $this->stayPeriod;
    }

    public function checkIn(): DateTimeImmutable
    {
        return $this->stayPeriod->checkIn();
    }

    public function checkOut(): DateTimeImmutable
    {
        return $this->stayPeriod->checkOut();
    }

    public function nights(): int
    {
        return $this->stayPeriod->nights();
    }

    public function adults(): int
    {
        return $this->adults;
    }

    public function children(): int
    {
        return $this->children;
    }

    public function totalGuests(): int
    {
        return $this->adults + $this->children;
    }

    public function checkedAt(): DateTimeImmutable
    {
        return $this->checkedAt;
    }

    /**
     * @return list<int>
     */
    public function roomIds(): array
    {
        return $this->roomIds;
    }

    public function roomCount(): int
    {
        return count($this->roomIds);
    }

    public function hasAvailableRooms(): bool
    {
        return $this->roomIds !== [];
    }

    /**
     * @param list<mixed> $roomIds
     */
    private function containsOnlyUniquePositiveIdentifiers(array $roomIds): bool
    {
        $seen = [];

        foreach ($roomIds as $roomId) {
            if (!is_int($roomId) || $roomId <= 0 || isset($seen[$roomId])) {
                return false;
            }

            $seen[$roomId] = true;
        }

        return true;
    }
}
