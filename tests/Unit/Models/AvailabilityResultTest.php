<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AvailabilityResult;
use App\Models\StayPeriod;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AvailabilityResultTest extends TestCase
{
    public function testItPreservesTheValidatedSearchAndAvailableRoomOrder(): void
    {
        $period = self::period();
        $checkedAt = self::timestamp('2026-08-01 12:00:00');
        $result = new AvailabilityResult($period, 2, 1, $checkedAt, [9, 3, 12]);

        self::assertSame($period, $result->stayPeriod());
        self::assertSame('2026-08-10', $result->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-13', $result->checkOut()->format('Y-m-d'));
        self::assertSame(3, $result->nights());
        self::assertSame(2, $result->adults());
        self::assertSame(1, $result->children());
        self::assertSame(3, $result->totalGuests());
        self::assertSame($checkedAt, $result->checkedAt());
        self::assertSame([9, 3, 12], $result->roomIds());
        self::assertSame(3, $result->roomCount());
        self::assertTrue($result->hasAvailableRooms());
    }

    public function testAnEmptyAvailabilityResultIsValid(): void
    {
        $result = new AvailabilityResult(
            self::period(),
            1,
            0,
            self::timestamp('2026-08-01 12:00:00'),
            [],
        );

        self::assertSame([], $result->roomIds());
        self::assertSame(0, $result->roomCount());
        self::assertFalse($result->hasAvailableRooms());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidData')]
    public function testItRejectsMalformedServiceOrRepositoryData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Availability result data is invalid.');

        self::availabilityResult($override);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidData(): iterable
    {
        yield 'zero adults' => [['adults' => 0]];
        yield 'negative children' => [['children' => -1]];
        yield 'non-UTC calculation time' => [[
            'checkedAt' => new DateTimeImmutable('2026-08-01 12:00:00 +05:45'),
        ]];
        yield 'non-list room identifiers' => [['roomIds' => [1 => 7]]];
        yield 'zero room identifier' => [['roomIds' => [0]]];
        yield 'string room identifier' => [['roomIds' => ['7']]];
        yield 'duplicate room identifier' => [['roomIds' => [7, 7]]];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function availabilityResult(array $override): AvailabilityResult
    {
        return new AvailabilityResult(...array_replace([
            'stayPeriod' => self::period(),
            'adults' => 2,
            'children' => 1,
            'checkedAt' => self::timestamp('2026-08-01 12:00:00'),
            'roomIds' => [7, 8],
        ], $override));
    }

    private static function period(): StayPeriod
    {
        return new StayPeriod(
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
        );
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
