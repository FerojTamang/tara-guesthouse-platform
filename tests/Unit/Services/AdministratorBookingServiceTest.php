<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\AdministratorBookingDetail;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingRoom;
use App\Repositories\BookingCatalogRepository;
use App\Services\AdministratorBookingService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdministratorBookingServiceTest extends TestCase
{
    public function testItListsAllOrOneCanonicalStatus(): void
    {
        $booking = self::booking();
        $repository = $this->createMock(BookingCatalogRepository::class);
        $repository->expects(self::exactly(2))
            ->method('findAll')
            ->willReturnCallback(
                static fn (?string $status): array => $status === null
                    || $status === Booking::STATUS_PENDING
                    ? [$booking]
                    : [],
            );
        $service = new AdministratorBookingService($repository);

        self::assertSame([$booking], $service->list());
        self::assertSame(
            [$booking],
            $service->list(Booking::STATUS_PENDING),
        );
    }

    public function testInvalidStatusFailsBeforeRepositoryAccess(): void
    {
        $repository = $this->createMock(BookingCatalogRepository::class);
        $repository->expects(self::never())->method('findAll');
        $service = new AdministratorBookingService($repository);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking status filter is invalid.');

        $service->list('completed');
    }

    public function testDetailAggregatesBookingRoomAndEvents(): void
    {
        $booking = self::booking();
        $room = self::room();
        $event = self::event();
        $repository = $this->createStub(BookingCatalogRepository::class);
        $repository->method('findById')->willReturn($booking);
        $repository->method('findRoomByBookingId')->willReturn($room);
        $repository->method('findEventsByBookingId')->willReturn([$event]);
        $service = new AdministratorBookingService($repository);

        $detail = $service->detail(31);

        self::assertInstanceOf(AdministratorBookingDetail::class, $detail);
        self::assertSame($booking, $detail->booking());
        self::assertSame($room, $detail->room());
        self::assertSame([$event], $detail->events());
    }

    public function testInvalidOrMissingDetailReturnsNull(): void
    {
        $repository = $this->createMock(BookingCatalogRepository::class);
        $repository->expects(self::once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);
        $repository->expects(self::never())
            ->method('findRoomByBookingId');
        $repository->expects(self::never())
            ->method('findEventsByBookingId');
        $service = new AdministratorBookingService($repository);

        self::assertNull($service->detail(0));
        self::assertNull($service->detail(999));
    }

    public function testStoredBookingWithoutRoomSnapshotFailsClosed(): void
    {
        $repository = $this->createStub(BookingCatalogRepository::class);
        $repository->method('findById')->willReturn(self::booking());
        $repository->method('findRoomByBookingId')->willReturn(null);
        $service = new AdministratorBookingService($repository);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking operation failed.');

        $service->detail(31);
    }

    private static function booking(): Booking
    {
        $createdAt = new DateTimeImmutable('2026-08-01 10:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-ADMINREAD00000001',
            null,
            'Guest Example',
            'guest@example.test',
            '+9779812345678',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            2,
            1,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            Booking::STATUS_PENDING,
            'Late arrival.',
            $createdAt,
            'v1',
            new DateTimeImmutable('2026-08-01 16:00:00 UTC'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            null,
        );
    }

    private static function room(): BookingRoom
    {
        return new BookingRoom(
            41,
            31,
            12,
            '6000.00',
            3,
            '18000.00',
            new DateTimeImmutable('2026-08-01 10:00:00 UTC'),
        );
    }

    private static function event(): BookingEvent
    {
        return new BookingEvent(
            51,
            31,
            'booking_created',
            null,
            Booking::STATUS_PENDING,
            null,
            null,
            null,
            new DateTimeImmutable('2026-08-01 10:00:00 UTC'),
        );
    }
}
