<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\RoomRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class RoomRepositoryTest extends DatabaseTestCase
{
    private RoomRepository $repository;

    /** @var array<string, int> */
    private array $roomTypeIds = [];

    /** @var array<string, int> */
    private array $roomIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 3);
        (new MigrationRunner(
            $this->connection,
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $this->roomTypeIds['deluxe'] = $this->insertRoomType(
            'Deluxe Room',
            'deluxe-room',
            'Active room type fixture.',
            '5000.00',
            2,
            1,
            true,
            '2026-07-22 01:02:03',
            '2026-07-22 02:03:04',
        );
        $this->roomTypeIds['retired'] = $this->insertRoomType(
            'Retired Room Type',
            'retired-room-type',
            null,
            '3000.00',
            1,
            0,
            false,
            '2026-07-22 03:04:05',
            '2026-07-22 04:05:06',
        );

        $this->roomIds['public'] = $this->insertRoom(
            $this->roomTypeIds['deluxe'],
            '101',
            'Garden Deluxe',
            'Garden-Deluxe',
            'Public room fixture.',
            '6200.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            '2026-07-22 05:06:07',
            '2026-07-22 06:07:08',
            null,
        );
        $this->roomIds['maintenance'] = $this->insertRoom(
            $this->roomTypeIds['deluxe'],
            '102',
            'Maintenance Room',
            'maintenance-room',
            null,
            '5100.00',
            2,
            0,
            Room::STATUS_MAINTENANCE,
            true,
            '2026-07-22 07:08:09',
            '2026-07-22 08:09:10',
            null,
        );
        $this->roomIds['draft'] = $this->insertRoom(
            $this->roomTypeIds['retired'],
            '103',
            'Draft Room',
            'draft-room',
            'Unpublished room fixture.',
            '3500.00',
            1,
            0,
            Room::STATUS_ACTIVE,
            false,
            '2026-07-22 09:10:11',
            '2026-07-22 10:11:12',
            null,
        );
        $this->roomIds['deleted'] = $this->insertRoom(
            $this->roomTypeIds['retired'],
            '104',
            'Deleted Room',
            'deleted-room',
            'Soft-deleted room fixture.',
            '3600.00',
            1,
            0,
            Room::STATUS_ACTIVE,
            true,
            '2026-07-22 11:12:13',
            '2026-07-22 12:13:14',
            '2026-07-22 13:14:15',
        );

        $this->repository = new RoomRepository($this->connection);
    }

    public function testItFindsByIdAndMapsTheCompleteRoomAndRoomTypeRecord(): void
    {
        $room = $this->repository->findById($this->roomIds['public']);

        self::assertInstanceOf(Room::class, $room);
        self::assertSame($this->roomIds['public'], $room->id());
        self::assertSame('101', $room->roomNumber());
        self::assertSame('Garden Deluxe', $room->name());
        self::assertSame('Garden-Deluxe', $room->slug());
        self::assertSame('Public room fixture.', $room->description());
        self::assertSame('6200.00', $room->pricePerNight());
        self::assertSame(2, $room->capacityAdults());
        self::assertSame(1, $room->capacityChildren());
        self::assertSame(Room::STATUS_ACTIVE, $room->operationalStatus());
        self::assertTrue($room->isActive());
        self::assertTrue($room->isPublished());
        self::assertFalse($room->isDeleted());
        self::assertEquals(new DateTimeImmutable('2026-07-22 05:06:07 UTC'), $room->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-07-22 06:07:08 UTC'), $room->updatedAt());
        self::assertSame('UTC', $room->createdAt()->getTimezone()->getName());
        self::assertSame('UTC', $room->updatedAt()->getTimezone()->getName());

        $roomType = $room->roomType();
        self::assertInstanceOf(RoomType::class, $roomType);
        self::assertSame($this->roomTypeIds['deluxe'], $roomType->id());
        self::assertSame('Deluxe Room', $roomType->name());
        self::assertSame('deluxe-room', $roomType->slug());
        self::assertSame('Active room type fixture.', $roomType->description());
        self::assertSame('5000.00', $roomType->basePrice());
        self::assertSame(2, $roomType->capacityAdults());
        self::assertSame(1, $roomType->capacityChildren());
        self::assertTrue($roomType->isActive());
        self::assertEquals(new DateTimeImmutable('2026-07-22 01:02:03 UTC'), $roomType->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-07-22 02:03:04 UTC'), $roomType->updatedAt());
    }

    public function testSlugLookupUsesTheApprovedDatabaseCollationWithoutNormalization(): void
    {
        $room = $this->repository->findBySlug('garden-deluxe');

        self::assertInstanceOf(Room::class, $room);
        self::assertSame($this->roomIds['public'], $room->id());
        self::assertSame('Garden-Deluxe', $room->slug());
    }

    public function testSoftDeletedRoomsAreExcludedByDefaultButCanBeReadExplicitly(): void
    {
        self::assertNull($this->repository->findById($this->roomIds['deleted']));
        self::assertNull($this->repository->findBySlug('deleted-room'));

        $deleted = $this->repository->findByIdIncludingDeleted($this->roomIds['deleted']);

        self::assertInstanceOf(Room::class, $deleted);
        self::assertTrue($deleted->isDeleted());
        self::assertEquals(new DateTimeImmutable('2026-07-22 13:14:15 UTC'), $deleted->deletedAt());
        self::assertFalse($deleted->roomType()->isActive());
        self::assertNull($deleted->roomType()->description());
    }

    public function testItListsCurrentAndPublicRoomsInDeterministicRoomNumberOrder(): void
    {
        $all = $this->repository->findAll();
        $public = $this->repository->findAllPublic();

        self::assertSame(['101', '102', '103'], array_map(
            static fn (Room $room): string => $room->roomNumber(),
            $all,
        ));
        self::assertSame(
            [Room::STATUS_ACTIVE, Room::STATUS_MAINTENANCE, Room::STATUS_ACTIVE],
            array_map(static fn (Room $room): string => $room->operationalStatus(), $all),
        );
        self::assertSame([true, true, false], array_map(
            static fn (Room $room): bool => $room->isPublished(),
            $all,
        ));
        self::assertSame(['101'], array_map(
            static fn (Room $room): string => $room->roomNumber(),
            $public,
        ));
    }

    public function testMissingAndInjectionShapedLookupsReturnNullWithoutMutation(): void
    {
        $before = $this->roomSnapshot();

        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findByIdIncludingDeleted(-1));
        self::assertNull($this->repository->findById(999999));
        self::assertNull($this->repository->findBySlug(''));
        self::assertNull($this->repository->findBySlug("x' OR 1=1 --"));

        self::assertSame($before, $this->roomSnapshot());
    }

    public function testDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_amenities', 'room_images', 'rooms']);

        try {
            $this->repository->findAll();
            self::fail('Expected a sanitized room listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertRoomType(
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
        string $createdAt,
        string $updatedAt,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types (name, slug, description, base_price, capacity_adults, capacity_children, '
            . 'is_active, created_at, updated_at) VALUES (:name, :slug, :description, :base_price, '
            . ':capacity_adults, :capacity_children, :is_active, :created_at, :updated_at)',
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'base_price' => $basePrice,
            'capacity_adults' => $capacityAdults,
            'capacity_children' => $capacityChildren,
            'is_active' => $active ? 1 : 0,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
        string $createdAt,
        string $updatedAt,
        ?string $deletedAt,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms (room_type_id, room_number, name, slug, description, price_per_night, '
            . 'capacity_adults, capacity_children, operational_status, is_published, created_at, updated_at, '
            . 'deleted_at) VALUES (:room_type_id, :room_number, :name, :slug, :description, :price_per_night, '
            . ':capacity_adults, :capacity_children, :operational_status, :is_published, :created_at, '
            . ':updated_at, :deleted_at)',
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => $roomNumber,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price_per_night' => $pricePerNight,
            'capacity_adults' => $capacityAdults,
            'capacity_children' => $capacityChildren,
            'operational_status' => $operationalStatus,
            'is_published' => $published ? 1 : 0,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function roomSnapshot(): string
    {
        $roomTypes = $this->connection->query('SELECT * FROM room_types ORDER BY id')->fetchAll();
        $rooms = $this->connection->query('SELECT * FROM rooms ORDER BY id')->fetchAll();

        return json_encode(['room_types' => $roomTypes, 'rooms' => $rooms], JSON_THROW_ON_ERROR);
    }
}
