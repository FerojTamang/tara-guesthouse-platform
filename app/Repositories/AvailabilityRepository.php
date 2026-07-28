<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\StayPeriod;
use DateTimeImmutable;

/**
 * Defines the persistence queries used to calculate room availability.
 */
interface AvailabilityRepository
{
    /**
     * Returns public, operationally active room identifiers that satisfy occupancy
     * and have no unexpired overlapping blocking booking.
     *
     * @return list<int>
     */
    public function findAvailableRoomIds(
        StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array;

    /**
     * Reports whether one room has an unexpired overlapping blocking booking.
     */
    public function hasBlockingOverlap(
        int $roomId,
        StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool;
}
