<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\AvailabilityException;
use App\Requests\RoomAvailabilityCalendarRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomAvailabilityCalendarRequestTest extends TestCase
{
    public function testExactNinetyThreeDayWindowAndTwelveMonthBoundaryAreAccepted(): void
    {
        $request = RoomAvailabilityCalendarRequest::fromInput(
            '2026-08-02',
            '2026-11-03',
            new DateTimeImmutable('2026-08-02 08:00:00 +05:45'),
        );

        self::assertSame(93, $request->window()->nights());

        $boundary = RoomAvailabilityCalendarRequest::fromInput(
            '2027-07-31',
            '2027-08-02',
            new DateTimeImmutable('2026-08-02 08:00:00 +05:45'),
        );
        self::assertSame('2027-08-02', $boundary->window()->checkOut()->format('Y-m-d'));
    }

    #[DataProvider('invalidRanges')]
    public function testMalformedPastReversedAndUnboundedRangesAreRejected(mixed $from, mixed $to): void
    {
        $this->expectException(AvailabilityException::class);
        $this->expectExceptionMessage('The calendar date range is invalid.');

        RoomAvailabilityCalendarRequest::fromInput(
            $from,
            $to,
            new DateTimeImmutable('2026-08-02 08:00:00 +05:45'),
        );
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function invalidRanges(): iterable
    {
        yield 'missing' => [null, null];
        yield 'malformed' => ['2026-8-02', '2026-08-03'];
        yield 'impossible' => ['2026-02-30', '2026-03-02'];
        yield 'same day' => ['2026-08-02', '2026-08-02'];
        yield 'past' => ['2026-08-01', '2026-08-03'];
        yield 'over 93 days' => ['2026-08-02', '2026-11-04'];
        yield 'over 12 months' => ['2027-08-01', '2027-08-03'];
    }
}
