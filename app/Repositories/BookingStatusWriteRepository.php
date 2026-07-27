<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BookingStatusSnapshot;
use DateTimeImmutable;

/**
 * Defines the booking status writes performed inside a caller-owned transaction.
 */
interface BookingStatusWriteRepository
{
    public function lockCurrent(int $bookingId): ?BookingStatusSnapshot;

    public function updateStatus(
        BookingStatusSnapshot $current,
        string $targetStatus,
        ?string $reason,
        DateTimeImmutable $changedAt,
    ): void;

    public function appendEvent(
        int $bookingId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        int $performedBy,
        ?string $reason,
        DateTimeImmutable $createdAt,
    ): void;
}
