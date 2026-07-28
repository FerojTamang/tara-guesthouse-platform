<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Services\BookingDeadlinePolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingDeadlinePolicyTest extends TestCase
{
    public function testItUsesOnlyTheExplicitlySuppliedDuration(): void
    {
        $policy = new BookingDeadlinePolicy(360);
        $acceptedAt = new DateTimeImmutable('2026-08-01 12:00:00 UTC');

        self::assertSame(360, $policy->minutes());
        self::assertSame(
            '2026-08-01 18:00:00',
            $policy->deadlineFrom($acceptedAt)->format('Y-m-d H:i:s'),
        );
    }

    public function testInvalidDurationFailsClosed(): void
    {
        foreach ([0, -1] as $minutes) {
            try {
                new BookingDeadlinePolicy($minutes);
                self::fail('Expected an invalid booking deadline duration.');
            } catch (BookingException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Booking operation failed.', $exception->getMessage());
            }
        }
    }

    public function testNonUtcAcceptanceTimeFailsClosed(): void
    {
        $policy = new BookingDeadlinePolicy(360);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking operation failed.');

        $policy->deadlineFrom(
            new DateTimeImmutable('2026-08-01 12:00:00 +05:45'),
        );
    }
}
