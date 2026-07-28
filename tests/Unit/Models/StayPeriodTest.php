<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\StayPeriod;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StayPeriodTest extends TestCase
{
    public function testItPreservesDatesAndCalculatesNights(): void
    {
        $checkIn = self::date('2026-08-10');
        $checkOut = self::date('2026-08-13');
        $period = new StayPeriod($checkIn, $checkOut);

        self::assertSame($checkIn, $period->checkIn());
        self::assertSame($checkOut, $period->checkOut());
        self::assertSame(3, $period->nights());
    }

    #[DataProvider('overlappingPeriods')]
    public function testItUsesTheCanonicalStrictOverlapRule(
        string $firstCheckIn,
        string $firstCheckOut,
        string $secondCheckIn,
        string $secondCheckOut,
    ): void {
        $first = new StayPeriod(self::date($firstCheckIn), self::date($firstCheckOut));
        $second = new StayPeriod(self::date($secondCheckIn), self::date($secondCheckOut));

        self::assertTrue($first->overlaps($second));
        self::assertTrue($second->overlaps($first));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function overlappingPeriods(): iterable
    {
        yield 'same period' => ['2026-08-10', '2026-08-13', '2026-08-10', '2026-08-13'];
        yield 'starts inside' => ['2026-08-10', '2026-08-13', '2026-08-12', '2026-08-15'];
        yield 'ends inside' => ['2026-08-10', '2026-08-13', '2026-08-08', '2026-08-11'];
        yield 'contains other' => ['2026-08-10', '2026-08-20', '2026-08-12', '2026-08-15'];
    }

    public function testAdjacentStaysDoNotOverlap(): void
    {
        $existing = new StayPeriod(self::date('2026-08-10'), self::date('2026-08-13'));
        $next = new StayPeriod(self::date('2026-08-13'), self::date('2026-08-15'));
        $previous = new StayPeriod(self::date('2026-08-08'), self::date('2026-08-10'));

        self::assertFalse($existing->overlaps($next));
        self::assertFalse($next->overlaps($existing));
        self::assertFalse($existing->overlaps($previous));
        self::assertFalse($previous->overlaps($existing));
    }

    /**
     * @param array{DateTimeImmutable, DateTimeImmutable} $dates
     */
    #[DataProvider('invalidPeriods')]
    public function testItRejectsInvalidDomainDates(array $dates): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stay period is invalid.');

        new StayPeriod(...$dates);
    }

    /**
     * @return iterable<string, array{array{DateTimeImmutable, DateTimeImmutable}}>
     */
    public static function invalidPeriods(): iterable
    {
        yield 'same day' => [[self::date('2026-08-10'), self::date('2026-08-10')]];
        yield 'reversed' => [[self::date('2026-08-11'), self::date('2026-08-10')]];
        yield 'check-in has time' => [[
            new DateTimeImmutable('2026-08-10 12:00:00 UTC'),
            self::date('2026-08-11'),
        ]];
        yield 'check-out is not UTC' => [[
            self::date('2026-08-10'),
            new DateTimeImmutable('2026-08-11 00:00:00 +05:45'),
        ]];
    }

    private static function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00 UTC');
    }
}
