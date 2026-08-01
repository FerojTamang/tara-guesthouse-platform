<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AdministratorDashboardMetrics;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorDashboardMetricsTest extends TestCase
{
    public function testZeroAndPositiveAggregateMetricsExposeExactIntegers(): void
    {
        $zero = $this->metrics();
        self::assertSame(0, $zero->activeRooms());
        self::assertSame(0, $zero->availableRooms());

        $metrics = $this->metrics(5, 2, 3, 4, 5, 6, 7, 8, 9);
        self::assertSame('2026-07-31 18:15:00', $metrics->rangeStart()->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-01 18:15:00', $metrics->rangeEnd()->format('Y-m-d H:i:s'));
        self::assertSame(5, $metrics->activeRooms());
        self::assertSame(2, $metrics->occupiedRooms());
        self::assertSame(3, $metrics->availableRooms());
        self::assertSame(3, $metrics->arrivalsToday());
        self::assertSame(4, $metrics->departuresToday());
        self::assertSame(5, $metrics->pendingBookings());
        self::assertSame(6, $metrics->confirmedBookings());
        self::assertSame(7, $metrics->pendingPaymentReviews());
        self::assertSame(8, $metrics->pendingReviewModeration());
        self::assertSame(9, $metrics->unreadNotifications());
    }

    #[DataProvider('invalidCounts')]
    public function testNegativeCountsAndNegativeAvailableFormulaAreRejected(
        int $active,
        int $occupied,
        int $arrivals,
        int $departures,
        int $pending,
        int $confirmed,
        int $payments,
        int $reviews,
        int $notifications,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->metrics($active, $occupied, $arrivals, $departures, $pending, $confirmed, $payments, $reviews, $notifications);
    }

    /** @return iterable<string, array<int, int>> */
    public static function invalidCounts(): iterable
    {
        $valid = [1, 0, 0, 0, 0, 0, 0, 0, 0];
        foreach (range(0, 8) as $index) {
            $case = $valid;
            $case[$index] = -1;
            yield 'negative field ' . $index => $case;
        }
        yield 'negative available formula' => [0, 1, 0, 0, 0, 0, 0, 0, 0];
    }

    private function metrics(
        int $active = 0, int $occupied = 0, int $arrivals = 0, int $departures = 0,
        int $pending = 0, int $confirmed = 0, int $payments = 0, int $reviews = 0, int $notifications = 0,
    ): AdministratorDashboardMetrics {
        return new AdministratorDashboardMetrics(new DateTimeImmutable('2026-07-31 18:15:00 UTC'), new DateTimeImmutable('2026-08-01 18:15:00 UTC'), $active, $occupied, $arrivals, $departures, $pending, $confirmed, $payments, $reviews, $notifications);
    }
}
