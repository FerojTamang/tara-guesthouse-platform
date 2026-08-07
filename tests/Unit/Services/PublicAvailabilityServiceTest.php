<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AvailabilityException;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\AvailabilityRepository;
use App\Presentation\RoomImageUrl;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Requests\AvailabilityRequest;
use App\Services\AvailabilityService;
use App\Services\PublicAvailabilityService;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicAvailabilityServiceTest extends TestCase
{
    public function testItMapsOnlyStillPublicRoomsInCalculatedOrder(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->roomIds = [8, 4, 12];
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willReturn([
            self::room(4, 'Garden Room', 'garden-room', '4500.00'),
            self::room(8, 'Deluxe Room', 'deluxe-room', '6000.00'),
        ]);
        $service = new PublicAvailabilityService(
            new AvailabilityService(
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $rooms,
        );

        $result = $service->search(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 2, 0),
        );

        self::assertSame([
            [
                'room_id' => 8,
                'name' => 'Deluxe Room',
                'slug' => 'deluxe-room',
                'is_available' => true,
                'currency' => 'NPR',
                'price_per_night' => '6000.00',
            ],
            [
                'room_id' => 4,
                'name' => 'Garden Room',
                'slug' => 'garden-room',
                'is_available' => true,
                'currency' => 'NPR',
                'price_per_night' => '4500.00',
            ],
        ], $result);
    }

    public function testRichAvailabilityBatchesRoomAmenitiesAndPrimaryImages(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->roomIds = [8, 4];
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())
            ->method('findPublicByIds')
            ->with([8, 4])
            ->willReturn([
                self::room(4, 'Garden Room', 'garden-room', '4500.00'),
                self::room(8, 'Deluxe Room', 'deluxe-room', '6000.00'),
            ]);
        $rooms->expects(self::once())
            ->method('findActiveAmenitiesByRoomIds')
            ->with([4, 8])
            ->willReturn([4 => [], 8 => []]);
        $rooms->expects(self::never())->method('findAllPublic');
        $rooms->expects(self::never())->method('findActiveAmenitiesByRoomId');
        $images = $this->createMock(RoomImageCatalogRepository::class);
        $images->expects(self::once())
            ->method('findPrimaryByRoomIds')
            ->with([4, 8])
            ->willReturn([]);
        $images->expects(self::never())->method('findPrimaryByRoomId');

        $service = new PublicAvailabilityService(
            new AvailabilityService(
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $rooms,
            new PublicRoomCatalogServiceProvider(
                static fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                    $rooms,
                    $images,
                    new RoomImageUrl(),
                ),
            ),
        );

        $context = $service->searchContext(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 2, 0),
        );

        self::assertSame([8, 4], array_column($context['rooms'], 'room_id'));
        self::assertSame([
            '/assets/images/room-placeholder.svg',
            '/assets/images/room-placeholder.svg',
        ], array_column($context['rooms'], 'primary_image_url'));
    }

    public function testRichSelectedRoomUsesListingProjectionWithoutLoadingTheFullImageGallery(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->roomIds = [4];
        $room = self::room(4, 'Garden Room', 'garden-room', '4500.00');
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())
            ->method('findPublicBySlug')
            ->with('garden-room')
            ->willReturn($room);
        $rooms->expects(self::once())
            ->method('findPublicByIds')
            ->with([4])
            ->willReturn([$room]);
        $rooms->expects(self::once())
            ->method('findActiveAmenitiesByRoomIds')
            ->with([4])
            ->willReturn([4 => []]);
        $rooms->expects(self::never())->method('findPublicById');
        $rooms->expects(self::never())->method('findAllPublic');
        $rooms->expects(self::never())->method('findActiveAmenitiesByRoomId');
        $images = $this->createMock(RoomImageCatalogRepository::class);
        $images->expects(self::once())
            ->method('findPrimaryByRoomIds')
            ->with([4])
            ->willReturn([]);
        $images->expects(self::never())->method('findPrimaryByRoomId');
        $images->expects(self::never())->method('findAllByRoomId');

        $service = new PublicAvailabilityService(
            new AvailabilityService(
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $rooms,
            new PublicRoomCatalogServiceProvider(
                static fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                    $rooms,
                    $images,
                    new RoomImageUrl(),
                ),
            ),
        );

        $context = $service->searchContext(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 2, 0),
            'garden-room',
        );

        self::assertSame(4, $context['rooms'][0]['room_id']);
        self::assertSame('garden-room', $context['selectedRoom']['slug']);
        self::assertTrue($context['selectedRoom']['is_available']);
    }

    public function testSelectedRoomUsesCanonicalPublicLookupAndExistingAvailabilityResult(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->roomIds = [4, 8];
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findPublicBySlug')->with('garden-room')->willReturn(
            self::room(4, 'Garden Room', 'garden-room', '4500.00'),
        );
        $rooms->expects(self::never())->method('findAllPublic');
        $service = new PublicAvailabilityService(
            new AvailabilityService($availability, static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00')),
            $rooms,
        );

        $context = $service->searchContext(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 2, 0),
            'garden-room',
        );

        self::assertCount(1, $context['rooms']);
        self::assertSame(4, $context['rooms'][0]['room_id']);
        self::assertSame('garden-room', $context['selectedRoom']['slug']);
        self::assertTrue($context['selectedRoom']['is_available']);
        self::assertSame(1, $availability->searchCalls);
    }

    public function testMalformedSelectedSlugFailsBeforeRepositoryOrAvailabilityAccess(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::never())->method('findPublicBySlug');
        $service = new PublicAvailabilityService(
            new AvailabilityService($availability, static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00')),
            $rooms,
        );

        try {
            $service->search(AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 1, 0), 'Garden Room');
            self::fail('Expected a safe selected-room validation failure.');
        } catch (AvailabilityException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('The selected room is unavailable.', $exception->getMessage());
            self::assertSame(0, $availability->searchCalls);
        }
    }

    public function testEmptyAvailabilityDoesNotReadTheRoomCatalogue(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::never())->method('findAllPublic');
        $service = new PublicAvailabilityService(
            new AvailabilityService(
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $rooms,
        );

        self::assertSame([], $service->search(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 1, 0),
        ));
    }

    public function testAvailabilityFailuresRemainSanitized(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->failure = new RuntimeException('SQLSTATE sensitive details');
        $service = new PublicAvailabilityService(
            new AvailabilityService(
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $this->createStub(RoomCatalogRepository::class),
        );

        try {
            $service->search(
                AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 1, 0),
            );
            self::fail('Expected a sanitized availability failure.');
        } catch (AvailabilityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Availability operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function testRoomCatalogueFailuresBecomeSanitizedAvailabilityFailures(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->roomIds = [4];
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willThrowException(
            new RuntimeException('private room query details'),
        );
        $service = new PublicAvailabilityService(
            new AvailabilityService(
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $rooms,
        );

        $this->expectException(AvailabilityException::class);
        $this->expectExceptionMessage('Availability operation failed.');

        $service->search(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-12', 1, 0),
        );
    }

    public function testCalendarUsesPublicRoomAndMergesClippedBlockingRanges(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $availability->periods = [
            new \App\Models\StayPeriod(self::timestamp('2026-08-01 00:00:00'), self::timestamp('2026-08-05 00:00:00')),
            new \App\Models\StayPeriod(self::timestamp('2026-08-05 00:00:00'), self::timestamp('2026-08-08 00:00:00')),
            new \App\Models\StayPeriod(self::timestamp('2026-08-12 00:00:00'), self::timestamp('2026-08-20 00:00:00')),
        ];
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findPublicBySlug')->willReturn(self::room(4, 'Garden Room', 'garden-room', '4500.00'));
        $service = new PublicAvailabilityService(
            new AvailabilityService($availability, static fn (): DateTimeImmutable => self::timestamp('2026-08-02 03:00:00')),
            $rooms,
            null,
            $availability,
            static fn (): DateTimeImmutable => self::timestamp('2026-08-02 03:00:00'),
        );

        $result = $service->calendar('garden-room', '2026-08-02', '2026-08-15');

        self::assertSame(['slug' => 'garden-room', 'name' => 'Garden Room'], $result['room']);
        self::assertSame('2026-08-02', $result['from']);
        self::assertSame('2026-08-15', $result['to']);
        self::assertSame([
            ['check_in' => '2026-08-02', 'check_out' => '2026-08-08'],
            ['check_in' => '2026-08-12', 'check_out' => '2026-08-15'],
        ], $result['unavailable_ranges']);
        self::assertSame(1, $availability->calendarCalls);
        self::assertSame('2026-08-02 03:00:00', $availability->calendarAsOf?->format('Y-m-d H:i:s'));
    }

    public function testCalendarRejectsMalformedAndMissingPublicSlugsSafely(): void
    {
        $availability = new PublicAvailabilityRepositoryFake();
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findPublicBySlug')->with('missing-room')->willReturn(null);
        $service = new PublicAvailabilityService(
            new AvailabilityService($availability),
            $rooms,
            null,
            $availability,
            static fn (): DateTimeImmutable => self::timestamp('2026-08-02 03:00:00'),
        );

        foreach (['Bad Slug', 'missing-room'] as $slug) {
            try {
                $service->calendar($slug, '2026-08-02', '2026-08-10');
                self::fail('Expected a safe calendar not-found response.');
            } catch (AvailabilityException $exception) {
                self::assertTrue($exception->isNotFound());
            }
        }

        self::assertSame(0, $availability->calendarCalls);
    }

    private static function room(
        int $id,
        string $name,
        string $slug,
        string $price,
    ): Room {
        $timestamp = self::timestamp('2026-07-01 10:00:00');
        $type = new RoomType(
            1,
            'Public Type',
            'public-type',
            null,
            '4000.00',
            4,
            2,
            true,
            $timestamp,
            $timestamp,
        );

        return new Room(
            $id,
            $type,
            'R-' . $id,
            $name,
            $slug,
            null,
            $price,
            4,
            2,
            Room::STATUS_ACTIVE,
            true,
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

final class PublicAvailabilityRepositoryFake implements AvailabilityRepository
{
    /** @var list<int> */
    public array $roomIds = [];

    public ?RuntimeException $failure = null;

    public int $searchCalls = 0;

    /** @var list<\App\Models\StayPeriod> */
    public array $periods = [];

    public int $calendarCalls = 0;

    public ?DateTimeImmutable $calendarAsOf = null;

    public function findAvailableRoomIds(
        \App\Models\StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array {
        $this->searchCalls++;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->roomIds;
    }

    public function hasBlockingOverlap(
        int $roomId,
        \App\Models\StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool {
        return false;
    }

    public function findBlockingStayPeriods(
        int $roomId,
        \App\Models\StayPeriod $window,
        DateTimeImmutable $asOf,
    ): array {
        $this->calendarCalls++;
        $this->calendarAsOf = $asOf;

        return $this->periods;
    }
}
