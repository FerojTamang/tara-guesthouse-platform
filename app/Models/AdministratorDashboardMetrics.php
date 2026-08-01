<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AdministratorDashboardMetrics
{
    public function __construct(
        private DateTimeImmutable $rangeStart,
        private DateTimeImmutable $rangeEnd,
        private int $activeRooms,
        private int $occupiedRooms,
        private int $arrivalsToday,
        private int $departuresToday,
        private int $pendingBookings,
        private int $confirmedBookings,
        private int $pendingPaymentReviews,
        private int $pendingReviewModeration,
        private int $unreadNotifications,
    ) {
        if ($rangeStart->getOffset() !== 0 || $rangeEnd->getOffset() !== 0 || $rangeEnd <= $rangeStart) {
            throw new InvalidArgumentException('Dashboard metrics are invalid.');
        }

        if (min(
            $activeRooms,
            $occupiedRooms,
            $arrivalsToday,
            $departuresToday,
            $pendingBookings,
            $confirmedBookings,
            $pendingPaymentReviews,
            $pendingReviewModeration,
            $unreadNotifications,
        ) < 0 || $occupiedRooms > $activeRooms) {
            throw new InvalidArgumentException('Dashboard metrics are invalid.');
        }
    }

    public function rangeStart(): DateTimeImmutable { return $this->rangeStart; }
    public function rangeEnd(): DateTimeImmutable { return $this->rangeEnd; }
    public function activeRooms(): int { return $this->activeRooms; }
    public function occupiedRooms(): int { return $this->occupiedRooms; }
    public function availableRooms(): int { return $this->activeRooms - $this->occupiedRooms; }
    public function arrivalsToday(): int { return $this->arrivalsToday; }
    public function departuresToday(): int { return $this->departuresToday; }
    public function pendingBookings(): int { return $this->pendingBookings; }
    public function confirmedBookings(): int { return $this->confirmedBookings; }
    public function pendingPaymentReviews(): int { return $this->pendingPaymentReviews; }
    public function pendingReviewModeration(): int { return $this->pendingReviewModeration; }
    public function unreadNotifications(): int { return $this->unreadNotifications; }
}
