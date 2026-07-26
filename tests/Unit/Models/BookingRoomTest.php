<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BookingRoom;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingRoomTest extends TestCase
{
    public function testItPreservesTheOneRoomPriceSnapshot(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-01 10:00:00 UTC');
        $bookingRoom = new BookingRoom(3, 7, 11, '6000.00', 3, '18000.00', $createdAt);

        self::assertSame(3, $bookingRoom->id());
        self::assertSame(7, $bookingRoom->bookingId());
        self::assertSame(11, $bookingRoom->roomId());
        self::assertSame('6000.00', $bookingRoom->pricePerNight());
        self::assertSame(3, $bookingRoom->nights());
        self::assertSame('18000.00', $bookingRoom->subtotal());
        self::assertSame($createdAt, $bookingRoom->createdAt());
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider('invalidData')]
    public function testItRejectsInvalidPersistenceData(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking room data is invalid.');

        new BookingRoom(...$arguments);
    }

    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function invalidData(): iterable
    {
        $valid = [3, 7, 11, '6000.00', 3, '18000.00', new DateTimeImmutable('2026-08-01 UTC')];

        yield 'invalid id' => [[0, ...array_slice($valid, 1)]];
        yield 'invalid booking id' => [[3, 0, ...array_slice($valid, 2)]];
        yield 'invalid room id' => [[3, 7, 0, ...array_slice($valid, 3)]];
        yield 'zero nightly price' => [[3, 7, 11, '0.00', 3, '18000.00', $valid[6]]];
        yield 'malformed nightly price' => [[3, 7, 11, '6000', 3, '18000.00', $valid[6]]];
        yield 'zero nights' => [[3, 7, 11, '6000.00', 0, '18000.00', $valid[6]]];
        yield 'zero subtotal' => [[3, 7, 11, '6000.00', 3, '0.00', $valid[6]]];
        yield 'non-UTC timestamp' => [[
            3,
            7,
            11,
            '6000.00',
            3,
            '18000.00',
            new DateTimeImmutable('2026-08-01 10:00:00 +05:45'),
        ]];
    }
}
