<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayPeriod;
use App\Repositories\AvailabilityRepository;
use App\Repositories\RoomCatalogRepository;
use App\Requests\GuestBookingRequest;
use App\Services\BookingService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BookingServiceTest extends TestCase
{
    public function testItPreparesAnEligibleNprSnapshot(): void
    {
        $room = self::room();
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findById')->willReturn($room);
        $availability = new BookingServiceAvailabilityFake();
        $checkedAt = self::timestamp('2026-08-01 12:00:00');
        $service = new BookingService(
            $rooms,
            $availability,
            static fn (): DateTimeImmutable => $checkedAt,
        );
        $request = self::bookingRequest();

        $quote = $service->prepare($request);

        self::assertSame($request, $quote->request());
        self::assertSame($room, $quote->room());
        self::assertSame(Booking::CURRENCY_NPR, $quote->currency());
        self::assertSame('4500.00', $quote->pricePerNight());
        self::assertSame(2, $quote->nights());
        self::assertSame('9000.00', $quote->roomAmount());
        self::assertSame('9000.00', $quote->totalAmount());
        self::assertSame($checkedAt, $quote->checkedAt());
        self::assertSame(1, $availability->overlapCalls);
        self::assertSame(12, $availability->roomId);
        self::assertSame($request->stayPeriod(), $availability->stayPeriod);
        self::assertSame($checkedAt, $availability->asOf);
    }

    public function testMissingRoomReturnsSafeNotFoundFailure(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findById')->willReturn(null);
        $availability = new BookingServiceAvailabilityFake();

        try {
            self::service($rooms, $availability)->prepare(self::bookingRequest());
            self::fail('Expected a missing room failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The selected room was not found.', $exception->getMessage());
            self::assertSame(0, $availability->overlapCalls);
        }
    }

    /**
     * @param array<string, mixed> $roomOverride
     * @param array<string, mixed> $requestOverride
     */
    #[DataProvider('ineligibleRooms')]
    public function testIneligibleRoomReturnsConflictBeforeOverlap(
        array $roomOverride,
        array $requestOverride,
    ): void {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findById')->willReturn(self::room($roomOverride));
        $availability = new BookingServiceAvailabilityFake();

        try {
            self::service($rooms, $availability)->prepare(
                self::bookingRequest($requestOverride),
            );
            self::fail('Expected an unavailable room failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(0, $availability->overlapCalls);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function ineligibleRooms(): iterable
    {
        yield 'maintenance' => [['status' => Room::STATUS_MAINTENANCE], []];
        yield 'unpublished' => [['published' => false], []];
        yield 'adult capacity' => [['capacityAdults' => 1], []];
        yield 'child capacity' => [['capacityChildren' => 0], ['children' => 1]];
    }

    public function testBlockingOverlapReturnsConflict(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findById')->willReturn(self::room());
        $availability = new BookingServiceAvailabilityFake();
        $availability->blocking = true;

        try {
            self::service($rooms, $availability)->prepare(self::bookingRequest());
            self::fail('Expected an overlap conflict.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(1, $availability->overlapCalls);
        }
    }

    public function testRepositoryFailureIsSanitized(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findById')->willThrowException(
            new RuntimeException('SQLSTATE[HY000] private booking details'),
        );

        try {
            self::service($rooms, new BookingServiceAvailabilityFake())
                ->prepare(self::bookingRequest());
            self::fail('Expected a sanitized system failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    public function testInvalidClockFailsBeforeRepositoryAccess(): void
    {
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::never())->method('findById');
        $service = new BookingService(
            $rooms,
            new BookingServiceAvailabilityFake(),
            static fn (): string => 'not-a-date',
        );

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking operation failed.');

        $service->prepare(self::bookingRequest());
    }

    private static function service(
        RoomCatalogRepository $rooms,
        AvailabilityRepository $availability,
    ): BookingService {
        return new BookingService(
            $rooms,
            $availability,
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function bookingRequest(array $override = []): GuestBookingRequest
    {
        $input = array_replace([
            'roomId' => 12,
            'guestFullName' => 'Asha Sharma',
            'guestEmail' => 'asha@example.test',
            'guestPhone' => '+9779800000000',
            'checkIn' => '2026-08-10',
            'checkOut' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'specialRequest' => null,
            'termsAccepted' => true,
            'policyVersion' => 'booking-policy-v1',
        ], $override);

        return GuestBookingRequest::fromInput(
            $input['roomId'],
            $input['guestFullName'],
            $input['guestEmail'],
            $input['guestPhone'],
            $input['checkIn'],
            $input['checkOut'],
            $input['adults'],
            $input['children'],
            $input['specialRequest'],
            $input['termsAccepted'],
            $input['policyVersion'],
        );
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function room(array $override = []): Room
    {
        $timestamp = self::timestamp('2026-07-01 10:00:00');
        $values = array_replace([
            'price' => '4500.00',
            'capacityAdults' => 2,
            'capacityChildren' => 1,
            'status' => Room::STATUS_ACTIVE,
            'published' => true,
        ], $override);
        $type = new RoomType(
            1,
            'Deluxe',
            'deluxe',
            null,
            '4500.00',
            4,
            2,
            true,
            $timestamp,
            $timestamp,
        );

        return new Room(
            12,
            $type,
            'D-12',
            'Deluxe Room',
            'deluxe-room-12',
            null,
            $values['price'],
            $values['capacityAdults'],
            $values['capacityChildren'],
            $values['status'],
            $values['published'],
            $timestamp,
            $timestamp,
            null,
        );
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}

final class BookingServiceAvailabilityFake implements AvailabilityRepository
{
    public bool $blocking = false;
    public int $overlapCalls = 0;
    public ?int $roomId = null;
    public ?StayPeriod $stayPeriod = null;
    public ?DateTimeImmutable $asOf = null;

    public function findAvailableRoomIds(
        StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array {
        return [];
    }

    public function hasBlockingOverlap(
        int $roomId,
        StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool {
        $this->overlapCalls++;
        $this->roomId = $roomId;
        $this->stayPeriod = $stayPeriod;
        $this->asOf = $asOf;

        return $this->blocking;
    }

    public function findBlockingStayPeriods(
        int $roomId,
        StayPeriod $window,
        DateTimeImmutable $asOf,
    ): array {
        return [];
    }
}
