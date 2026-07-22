<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Room;
use App\Models\RoomType;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RoomTest extends TestCase
{
    public function testItDefinesExactlyTheApprovedOperationalStatuses(): void
    {
        $reflection = new ReflectionClass(Room::class);
        $statuses = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->isPublic() && str_starts_with($constant->getName(), 'STATUS_')) {
                $statuses[$constant->getName()] = $constant->getValue();
            }
        }

        self::assertSame([
            'STATUS_ACTIVE' => 'active',
            'STATUS_MAINTENANCE' => 'maintenance',
            'STATUS_INACTIVE' => 'inactive',
        ], $statuses);
        self::assertNotContains('available', $statuses);
        self::assertNotContains('reserved', $statuses);
        self::assertNotContains('occupied', $statuses);
    }

    public function testItExposesImmutableStoredRoomDataAndExactMoney(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $updatedAt = new DateTimeImmutable('2026-07-22 02:03:04 UTC');
        $roomType = $this->roomType($createdAt, $updatedAt);
        $room = new Room(
            8,
            $roomType,
            'A-101',
            'Garden Deluxe',
            'garden-deluxe-a-101',
            'Ground-floor room.',
            '4750.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            $createdAt,
            $updatedAt,
            null,
        );

        self::assertTrue((new ReflectionClass($room))->isReadOnly());
        self::assertSame(8, $room->id());
        self::assertSame($roomType, $room->roomType());
        self::assertSame('A-101', $room->roomNumber());
        self::assertSame('Garden Deluxe', $room->name());
        self::assertSame('garden-deluxe-a-101', $room->slug());
        self::assertSame('Ground-floor room.', $room->description());
        self::assertSame('4750.00', $room->pricePerNight());
        self::assertIsString($room->pricePerNight());
        self::assertSame(2, $room->capacityAdults());
        self::assertSame(1, $room->capacityChildren());
        self::assertSame(Room::STATUS_ACTIVE, $room->operationalStatus());
        self::assertTrue($room->isActive());
        self::assertFalse($room->isUnderMaintenance());
        self::assertFalse($room->isInactive());
        self::assertTrue($room->isPublished());
        self::assertFalse($room->isDeleted());
        self::assertNull($room->deletedAt());
        self::assertSame($createdAt, $room->createdAt());
        self::assertSame($updatedAt, $room->updatedAt());

        $property = new \ReflectionProperty($room, 'roomNumber');
        $this->expectException(Error::class);
        $property->setValue($room, 'A-102');
    }

    #[DataProvider('stateProvider')]
    public function testStateHelpersOnlyReflectStoredOperationalAndPublicationState(
        string $status,
        bool $published,
        bool $active,
        bool $maintenance,
        bool $inactive,
    ): void {
        $room = $this->room(status: $status, published: $published);

        self::assertSame($active, $room->isActive());
        self::assertSame($maintenance, $room->isUnderMaintenance());
        self::assertSame($inactive, $room->isInactive());
        self::assertSame($published, $room->isPublished());
        self::assertFalse(method_exists($room, 'isAvailable'));
        self::assertFalse(method_exists($room, 'isReserved'));
        self::assertFalse(method_exists($room, 'isOccupied'));
    }

    public function testItRecognizesSoftDeletedStoredState(): void
    {
        $deletedAt = new DateTimeImmutable('2026-07-22 03:04:05 UTC');
        $room = $this->room(deletedAt: $deletedAt);

        self::assertTrue($room->isDeleted());
        self::assertSame($deletedAt, $room->deletedAt());
    }

    #[DataProvider('invalidDataProvider')]
    public function testItRejectsInvalidPersistenceData(
        int $id,
        string $roomNumber,
        string $name,
        string $slug,
        string $price,
        int $adults,
        int $children,
        string $status,
        bool $nonUtc,
    ): void {
        $utc = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $timestamp = $nonUtc ? new DateTimeImmutable('2026-07-22 01:02:03 Asia/Kathmandu') : $utc;

        $this->expectException(InvalidArgumentException::class);
        new Room(
            $id,
            $this->roomType($utc, $utc),
            $roomNumber,
            $name,
            $slug,
            null,
            $price,
            $adults,
            $children,
            $status,
            true,
            $timestamp,
            $utc,
            null,
        );
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool, bool}>
     */
    public static function stateProvider(): iterable
    {
        yield 'active published' => [Room::STATUS_ACTIVE, true, true, false, false];
        yield 'maintenance published' => [Room::STATUS_MAINTENANCE, true, false, true, false];
        yield 'inactive unpublished' => [Room::STATUS_INACTIVE, false, false, false, true];
    }

    /**
     * @return iterable<string, array{int, string, string, string, string, int, int, string, bool}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'invalid identifier' => [
            0, '101', 'Standard 101', 'standard-101', '2500.00', 1, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'empty room number' => [
            1, '', 'Standard 101', 'standard-101', '2500.00', 1, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'empty name' => [
            1, '101', '', 'standard-101', '2500.00', 1, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'empty slug' => [
            1, '101', 'Standard 101', '', '2500.00', 1, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'zero price' => [
            1, '101', 'Standard 101', 'standard-101', '0.00', 1, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'invalid price precision' => [
            1, '101', 'Standard 101', 'standard-101', '2500', 1, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'zero adults' => [
            1, '101', 'Standard 101', 'standard-101', '2500.00', 0, 0, Room::STATUS_ACTIVE, false,
        ];
        yield 'negative children' => [
            1, '101', 'Standard 101', 'standard-101', '2500.00', 1, -1, Room::STATUS_ACTIVE, false,
        ];
        yield 'availability is not status' => [
            1, '101', 'Standard 101', 'standard-101', '2500.00', 1, 0, 'available', false,
        ];
        yield 'wrong case status' => [
            1, '101', 'Standard 101', 'standard-101', '2500.00', 1, 0, 'Active', false,
        ];
        yield 'non UTC timestamp' => [
            1, '101', 'Standard 101', 'standard-101', '2500.00', 1, 0, Room::STATUS_ACTIVE, true,
        ];
    }

    private function room(
        string $status = Room::STATUS_ACTIVE,
        bool $published = true,
        ?DateTimeImmutable $deletedAt = null,
    ): Room {
        $timestamp = new DateTimeImmutable('2026-07-22 01:02:03 UTC');

        return new Room(
            1,
            $this->roomType($timestamp, $timestamp),
            '101',
            'Standard 101',
            'standard-101',
            null,
            '2500.00',
            1,
            0,
            $status,
            $published,
            $timestamp,
            $timestamp,
            $deletedAt,
        );
    }

    private function roomType(DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): RoomType
    {
        return new RoomType(2, 'Standard', 'standard', null, '2500.00', 1, 0, true, $createdAt, $updatedAt);
    }
}
