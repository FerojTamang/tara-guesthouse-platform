<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\BookingEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingEventTest extends TestCase
{
    public function testItPreservesAppendOnlyEventData(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-01 10:00:00 UTC');
        $event = new BookingEvent(
            5,
            7,
            'booking_cancelled',
            Booking::STATUS_PENDING,
            Booking::STATUS_CANCELLED,
            2,
            'Guest requested cancellation.',
            '{"source":"administrator"}',
            $createdAt,
        );

        self::assertSame(5, $event->id());
        self::assertSame(7, $event->bookingId());
        self::assertSame('booking_cancelled', $event->eventType());
        self::assertSame(Booking::STATUS_PENDING, $event->fromStatus());
        self::assertSame(Booking::STATUS_CANCELLED, $event->toStatus());
        self::assertSame(2, $event->performedBy());
        self::assertSame('Guest requested cancellation.', $event->reason());
        self::assertSame('{"source":"administrator"}', $event->metadataJson());
        self::assertSame($createdAt, $event->createdAt());
    }

    public function testGuestOrSystemEventMayHaveNullableTransitionActorAndMetadata(): void
    {
        $event = new BookingEvent(
            1,
            7,
            'booking_created',
            null,
            Booking::STATUS_PENDING,
            null,
            null,
            null,
            new DateTimeImmutable('2026-08-01 10:00:00 UTC'),
        );

        self::assertNull($event->fromStatus());
        self::assertSame(Booking::STATUS_PENDING, $event->toStatus());
        self::assertNull($event->performedBy());
        self::assertNull($event->reason());
        self::assertNull($event->metadataJson());
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider('invalidData')]
    public function testItRejectsInvalidPersistenceData(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking event data is invalid.');

        new BookingEvent(...$arguments);
    }

    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function invalidData(): iterable
    {
        $createdAt = new DateTimeImmutable('2026-08-01 10:00:00 UTC');

        yield 'invalid id' => [[
            0, 7, 'booking_created', null, Booking::STATUS_PENDING, null, null, null, $createdAt,
        ]];
        yield 'invalid booking id' => [[
            1, 0, 'booking_created', null, Booking::STATUS_PENDING, null, null, null, $createdAt,
        ]];
        yield 'blank event type' => [[
            1, 7, '', null, Booking::STATUS_PENDING, null, null, null, $createdAt,
        ]];
        yield 'untrimmed event type' => [[
            1, 7, ' booking_created ', null, Booking::STATUS_PENDING, null, null, null, $createdAt,
        ]];
        yield 'invalid from status' => [[
            1, 7, 'booking_changed', 'completed', Booking::STATUS_PENDING, null, null, null, $createdAt,
        ]];
        yield 'invalid to status' => [[
            1, 7, 'booking_changed', Booking::STATUS_PENDING, 'completed', null, null, null, $createdAt,
        ]];
        yield 'invalid actor id' => [[
            1, 7, 'booking_changed', Booking::STATUS_PENDING, Booking::STATUS_CANCELLED, 0, null, null, $createdAt,
        ]];
        yield 'invalid metadata json' => [[
            1, 7, 'booking_created', null, Booking::STATUS_PENDING, null, null, '{broken', $createdAt,
        ]];
        yield 'non-UTC timestamp' => [[
            1,
            7,
            'booking_created',
            null,
            Booking::STATUS_PENDING,
            null,
            null,
            null,
            new DateTimeImmutable('2026-08-01 10:00:00 +05:45'),
        ]];
    }
}
