<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PublicRoomCatalogException;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Presentation\RoomImageUrl;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Services\PublicRoomCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicRoomCatalogServiceTest extends TestCase
{
    public function testItMapsEligibleRoomsAndFallsBackForUnsafeImages(): void
    {
        $room = $this->room();
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willReturn([$room]);
        $rooms->method('findActiveAmenitiesByRoomIds')->willReturn([1 => [$this->amenity()]]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $images->method('findPrimaryByRoomIds')->willReturn([1 => $this->image('bad-path')]);

        $item = (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->list()[0];

        self::assertSame('Garden Room', $item['name']);
        self::assertSame('6200.00', $item['pricePerNight']);
        self::assertSame('Wi-Fi', $item['amenities'][0]['name']);
        self::assertSame('/assets/images/room-placeholder.svg', $item['primaryImageUrl']);
    }

    public function testListingUsesOneBulkRelationLookupInsteadOfPerRoomQueries(): void
    {
        $first = $this->room(1, 'First Room', 'first-room');
        $second = $this->room(2, 'Second Room', 'second-room');
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findAllPublic')->willReturn([$first, $second]);
        $rooms->expects(self::once())
            ->method('findActiveAmenitiesByRoomIds')
            ->with([1, 2])
            ->willReturn([1 => [$this->amenity()], 2 => []]);
        $rooms->expects(self::never())->method('findActiveAmenitiesByRoomId');
        $images = $this->createMock(RoomImageCatalogRepository::class);
        $images->expects(self::once())
            ->method('findPrimaryByRoomIds')
            ->with([1, 2])
            ->willReturn([2 => $this->image('uploads/rooms/' . str_repeat('b', 64) . '.png', 2)]);
        $images->expects(self::never())->method('findPrimaryByRoomId');

        $items = (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->list();

        self::assertCount(2, $items);
        self::assertSame('Wi-Fi', $items[0]['amenities'][0]['name']);
        self::assertSame('/assets/images/room-placeholder.svg', $items[0]['primaryImageUrl']);
        self::assertSame('/uploads/rooms/' . str_repeat('b', 64) . '.png', $items[1]['primaryImageUrl']);
    }

    public function testMalformedSlugIsRejectedBeforeRepositoryAccess(): void
    {
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::never())->method('findPublicBySlug');
        $service = new PublicRoomCatalogService(
            $rooms,
            $this->createStub(RoomImageCatalogRepository::class),
            new RoomImageUrl(),
        );

        $this->expectException(PublicRoomCatalogException::class);
        $service->get('Garden Room');
    }

    public function testRepositoryFailureBecomesSafePublicException(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willThrowException(new RuntimeException('SQLSTATE secret C:\\storage'));
        $service = new PublicRoomCatalogService(
            $rooms,
            $this->createStub(RoomImageCatalogRepository::class),
            new RoomImageUrl(),
        );

        try {
            $service->list();
            self::fail('Expected a safe public failure.');
        } catch (PublicRoomCatalogException $exception) {
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('storage', $exception->getMessage());
        }
    }

    public function testNoPrimaryImageUsesPlaceholder(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willReturn([$this->room()]);
        $rooms->method('findActiveAmenitiesByRoomIds')->willReturn([1 => []]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $images->method('findPrimaryByRoomIds')->willReturn([]);

        self::assertSame(
            '/assets/images/room-placeholder.svg',
            (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->list()[0]['primaryImageUrl'],
        );
    }

    public function testPublicRoomCanBeProjectedByValidatedIdentifierWithoutScanningTheCatalogue(): void
    {
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findPublicById')->with(1)->willReturn($this->room());
        $rooms->expects(self::never())->method('findAllPublic');
        $rooms->method('findActiveAmenitiesByRoomId')->willReturn([]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $images->method('findPrimaryByRoomId')->willReturn(null);
        $images->method('findAllByRoomId')->willReturn([]);

        $item = (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->getById(1);

        self::assertSame('Garden Room', $item['name']);
        self::assertArrayNotHasKey('roomNumber', $item);
        self::assertArrayNotHasKey('operationalStatus', $item);
        self::assertArrayNotHasKey('isPublished', $item);
    }

    public function testRequestedRoomBatchUsesBulkQueriesAndPreservesRequestedOrder(): void
    {
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findPublicByIds')->with([2, 1])->willReturn([
            $this->room(1, 'First Room', 'first-room'),
            $this->room(2, 'Second Room', 'second-room'),
        ]);
        $rooms->expects(self::once())->method('findActiveAmenitiesByRoomIds')->with([1, 2])->willReturn([1 => [], 2 => []]);
        $images = $this->createMock(RoomImageCatalogRepository::class);
        $images->expects(self::once())->method('findPrimaryByRoomIds')->with([1, 2])->willReturn([]);

        $items = (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->getManyByIds([2, 1, 2, 0]);

        self::assertSame([2, 1], array_keys($items));
        self::assertSame('Second Room', $items[2]['name']);
        self::assertSame('First Room', $items[1]['name']);
    }

    public function testDetailPreservesSafeImageOrderAndRemovesDuplicateOrUnsafeUrls(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findPublicBySlug')->willReturn($this->room());
        $rooms->method('findActiveAmenitiesByRoomId')->willReturn([]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $primary = $this->image('uploads/rooms/' . str_repeat('a', 64) . '.png');
        $images->method('findPrimaryByRoomId')->willReturn($primary);
        $images->method('findAllByRoomId')->willReturn([
            $primary,
            $this->image('uploads/rooms/' . str_repeat('b', 64) . '.png'),
            $primary,
            $this->image('unsafe-path'),
        ]);

        $item = (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->get('garden-room');

        self::assertSame(2, $item['imageCount']);
        self::assertSame([
            '/uploads/rooms/' . str_repeat('a', 64) . '.png',
            '/uploads/rooms/' . str_repeat('b', 64) . '.png',
        ], array_column($item['images'], 'url'));
        self::assertTrue($item['images'][0]['isPrimary']);
        self::assertFalse($item['images'][1]['isPrimary']);
    }

    private function room(int $id = 1, string $name = 'Garden Room', string $slug = 'garden-room'): Room
    {
        $time = new DateTimeImmutable('2026-07-23 UTC');

        return new Room(
            $id,
            new RoomType(1, 'Deluxe', 'deluxe', null, '5000.00', 2, 1, true, $time, $time),
            (string) (100 + $id),
            $name,
            $slug,
            'A <nice> room',
            '6200.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            $time,
            $time,
            null,
        );
    }

    private function amenity(): Amenity
    {
        $time = new DateTimeImmutable('2026-07-23 UTC');
        return new Amenity(1, 'Wi-Fi', null, true, $time, $time);
    }

    private function image(string $path, int $roomId = 1): RoomImage
    {
        return new RoomImage(1, $roomId, $path, null, 0, true, new DateTimeImmutable('2026-07-23 UTC'));
    }
}
