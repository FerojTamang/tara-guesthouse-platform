<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AvailabilityException;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\AvailabilityRepository;
use App\Repositories\RoomCatalogRepository;
use App\Requests\AvailabilityRequest;
use App\Services\AvailabilityService;
use App\Services\PublicAvailabilityService;
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
                'operational_status' => Room::STATUS_ACTIVE,
                'is_published' => true,
                'is_available' => true,
                'currency' => 'NPR',
                'price_per_night' => '6000.00',
            ],
            [
                'room_id' => 4,
                'name' => 'Garden Room',
                'slug' => 'garden-room',
                'operational_status' => Room::STATUS_ACTIVE,
                'is_published' => true,
                'is_available' => true,
                'currency' => 'NPR',
                'price_per_night' => '4500.00',
            ],
        ], $result);
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
}
