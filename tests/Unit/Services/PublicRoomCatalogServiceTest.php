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
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willReturn([$this->room()]);
        $rooms->method('findActiveAmenitiesByRoomId')->willReturn([$this->amenity()]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $images->method('findPrimaryByRoomId')->willReturn($this->image('bad-path'));
        $service = new PublicRoomCatalogService($rooms, $images, new RoomImageUrl());
        $item = $service->list()[0];
        self::assertSame('Garden Room', $item['name']);
        self::assertSame('6200.00', $item['pricePerNight']);
        self::assertSame('Wi-Fi', $item['amenities'][0]['name']);
        self::assertSame('/assets/images/room-placeholder.svg', $item['primaryImageUrl']);
    }

    public function testMalformedSlugIsRejectedBeforeRepositoryAccess(): void
    {
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::never())->method('findPublicBySlug');
        $service = new PublicRoomCatalogService($rooms, $this->createStub(RoomImageCatalogRepository::class), new RoomImageUrl());
        $this->expectException(PublicRoomCatalogException::class);
        $service->get('Garden Room');
    }

    public function testRepositoryFailureBecomesSafePublicException(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willThrowException(new RuntimeException('SQLSTATE secret C:\\storage'));
        $service = new PublicRoomCatalogService($rooms, $this->createStub(RoomImageCatalogRepository::class), new RoomImageUrl());
        try { $service->list(); self::fail('Expected a safe public failure.'); } catch (PublicRoomCatalogException $exception) {
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('storage', $exception->getMessage());
        }
    }

    public function testNoPrimaryImageUsesPlaceholder(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class); $rooms->method('findAllPublic')->willReturn([$this->room()]); $rooms->method('findActiveAmenitiesByRoomId')->willReturn([]);
        $images = $this->createStub(RoomImageCatalogRepository::class); $images->method('findPrimaryByRoomId')->willReturn(null);
        self::assertSame('/assets/images/room-placeholder.svg', (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->list()[0]['primaryImageUrl']);
    }

    public function testPublicRoomCanBeProjectedByValidatedIdentifier(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findAllPublic')->willReturn([$this->room()]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $images->method('findPrimaryByRoomId')->willReturn(null);
        $images->method('findAllByRoomId')->willReturn([]);

        $item = (new PublicRoomCatalogService($rooms, $images, new RoomImageUrl()))->getById(1);

        self::assertSame('Garden Room', $item['name']);
        self::assertArrayNotHasKey('roomNumber', $item);
        self::assertArrayNotHasKey('operationalStatus', $item);
        self::assertArrayNotHasKey('isPublished', $item);
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

    private function room(): Room { $time = new DateTimeImmutable('2026-07-23 UTC'); return new Room(1, new RoomType(1, 'Deluxe', 'deluxe', null, '5000.00', 2, 1, true, $time, $time), '101', 'Garden Room', 'garden-room', 'A <nice> room', '6200.00', 2, 1, Room::STATUS_ACTIVE, true, $time, $time, null); }
    private function amenity(): Amenity { $time = new DateTimeImmutable('2026-07-23 UTC'); return new Amenity(1, 'Wi-Fi', null, true, $time, $time); }
    private function image(string $path): RoomImage { return new RoomImage(1, 1, $path, null, 0, true, new DateTimeImmutable('2026-07-23 UTC')); }
}
