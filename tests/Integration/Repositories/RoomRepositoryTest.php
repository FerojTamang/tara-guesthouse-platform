<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\ConnectionFactory;
use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Exceptions\RoomCatalogException;
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

    /** @var array<string, int> */
    private array $amenityIds = [];

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

        $this->amenityIds['wifi'] = $this->insertAmenity('Wi-Fi', 'wifi', true);
        $this->amenityIds['parking'] = $this->insertAmenity('Parking', 'parking', false);

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

        $this->assignAmenity($this->roomIds['public'], $this->amenityIds['wifi']);
        $this->assignAmenity($this->roomIds['public'], $this->amenityIds['parking']);

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

    public function testItLocksAndReloadsTheCompleteCurrentRoomInsideATransaction(): void
    {
        $this->connection->beginTransaction();

        try {
            $room = $this->repository->lockCurrentById($this->roomIds['public']);

            self::assertInstanceOf(Room::class, $room);
            self::assertSame($this->roomIds['public'], $room->id());
            self::assertSame('101', $room->roomNumber());
            self::assertSame('Garden Deluxe', $room->name());
            self::assertSame($this->roomTypeIds['deluxe'], $room->roomType()->id());
            self::assertSame('Deluxe Room', $room->roomType()->name());
            self::assertTrue($this->connection->inTransaction());
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testRoomLockRequiresAnActiveTransaction(): void
    {
        try {
            $this->repository->lockCurrentById($this->roomIds['public']);
            self::fail('Expected a transaction-precondition failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room lock failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('rooms', $exception->getMessage());
        }
    }

    public function testRoomLockReturnsNullForInvalidMissingAndSoftDeletedRooms(): void
    {
        $this->connection->beginTransaction();

        try {
            self::assertNull($this->repository->lockCurrentById(0));
            self::assertNull($this->repository->lockCurrentById(-1));
            self::assertNull($this->repository->lockCurrentById(999999));
            self::assertNull($this->repository->lockCurrentById($this->roomIds['deleted']));
            self::assertTrue($this->connection->inTransaction());
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testCompetingConnectionCannotLockTheSameRoomButCanLockADifferentRoom(): void
    {
        $secondConnection = (new ConnectionFactory($this->databaseConfiguration))->create();
        $secondConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $secondRepository = new RoomRepository($secondConnection);
        $this->connection->beginTransaction();

        try {
            self::assertInstanceOf(
                Room::class,
                $this->repository->lockCurrentById($this->roomIds['public']),
            );

            $secondConnection->beginTransaction();

            try {
                $secondRepository->lockCurrentById($this->roomIds['public']);
                self::fail('Expected the competing room lock to time out.');
            } catch (DatabaseException $exception) {
                self::assertSame('The room lock failed.', $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
                self::assertTrue($secondConnection->inTransaction());
            } finally {
                if ($secondConnection->inTransaction()) {
                    $secondConnection->rollBack();
                }
            }

            $secondConnection->beginTransaction();

            try {
                $otherRoom = $secondRepository->lockCurrentById($this->roomIds['maintenance']);
                self::assertInstanceOf(Room::class, $otherRoom);
                self::assertSame($this->roomIds['maintenance'], $otherRoom->id());
            } finally {
                $secondConnection->rollBack();
            }
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if ($secondConnection->inTransaction()) {
                $secondConnection->rollBack();
            }
        }
    }

    public function testRollbackAndCommitReleaseTheRoomLock(): void
    {
        $secondConnection = (new ConnectionFactory($this->databaseConfiguration))->create();
        $secondRepository = new RoomRepository($secondConnection);

        foreach (['rollBack', 'commit'] as $release) {
            $this->connection->beginTransaction();
            self::assertInstanceOf(
                Room::class,
                $this->repository->lockCurrentById($this->roomIds['public']),
            );
            $this->connection->{$release}();

            $secondConnection->beginTransaction();

            try {
                $room = $secondRepository->lockCurrentById($this->roomIds['public']);
                self::assertInstanceOf(Room::class, $room);
                self::assertSame($this->roomIds['public'], $room->id());
            } finally {
                $secondConnection->rollBack();
            }
        }

        self::assertFalse($this->connection->inTransaction());
        self::assertFalse($secondConnection->inTransaction());
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

    public function testItCreatesUpdatesAndSynchronizesAmenityAssignments(): void
    {
        $created = $this->repository->create(
            $this->roomTypeIds['deluxe'],
            '201',
            'Courtyard Suite',
            'courtyard-suite',
            "First line\nSecond line",
            '7200.50',
            3,
            2,
            Room::STATUS_ACTIVE,
            false,
        );
        $this->repository->replaceAmenities(
            $created->id(),
            [$this->amenityIds['parking'], $this->amenityIds['wifi']],
        );

        self::assertSame('201', $created->roomNumber());
        self::assertSame('7200.50', $created->pricePerNight());
        self::assertFalse($created->isPublished());
        self::assertSame(
            [$this->amenityIds['wifi'], $this->amenityIds['parking']],
            $this->repository->findAmenityIds($created->id()),
        );

        $updated = $this->repository->update(
            $created->id(),
            $this->roomTypeIds['retired'],
            '202',
            'Courtyard Suite Updated',
            'courtyard-suite-updated',
            null,
            '7300.00',
            2,
            0,
            Room::STATUS_MAINTENANCE,
            true,
        );
        $this->repository->replaceAmenities($created->id(), [$this->amenityIds['parking']]);

        self::assertInstanceOf(Room::class, $updated);
        self::assertSame('202', $updated->roomNumber());
        self::assertSame('courtyard-suite-updated', $updated->slug());
        self::assertSame(Room::STATUS_MAINTENANCE, $updated->operationalStatus());
        self::assertTrue($updated->isPublished());
        self::assertFalse($updated->roomType()->isActive());
        self::assertSame(
            [$this->amenityIds['parking']],
            $this->repository->findAmenityIds($created->id()),
        );

        $this->repository->replaceAmenities($created->id(), []);
        self::assertSame([], $this->repository->findAmenityIds($created->id()));
    }

    public function testMissingAndDeletedWriteTargetsReturnNullWithoutMutation(): void
    {
        $before = $this->roomSnapshot();

        self::assertNull($this->repository->update(
            999999,
            $this->roomTypeIds['deluxe'],
            '999',
            'Missing Room',
            'missing-room',
            null,
            '5000.00',
            1,
            0,
            Room::STATUS_ACTIVE,
            false,
        ));
        self::assertNull($this->repository->update(
            $this->roomIds['deleted'],
            $this->roomTypeIds['deluxe'],
            '998',
            'Deleted Room Update',
            'deleted-room-update',
            null,
            '5000.00',
            1,
            0,
            Room::STATUS_ACTIVE,
            false,
        ));
        self::assertNull($this->repository->softDelete(999999));
        self::assertNull($this->repository->softDelete($this->roomIds['deleted']));

        self::assertSame($before, $this->roomSnapshot());
    }

    public function testSoftDeleteRetainsHistoricalAmenityAssignments(): void
    {
        $deleted = $this->repository->softDelete($this->roomIds['public']);

        self::assertInstanceOf(Room::class, $deleted);
        self::assertTrue($deleted->isDeleted());
        self::assertNull($this->repository->findById($this->roomIds['public']));
        self::assertSame(
            [$this->amenityIds['wifi'], $this->amenityIds['parking']],
            $this->repository->findAmenityIds($this->roomIds['public']),
        );
    }

    public function testDuplicateRoomNumberAndSlugBecomeSafeConflicts(): void
    {
        $cases = [
            ['101', 'unique-room-slug', 'A room with this room number already exists.'],
            ['999', 'garden-deluxe', 'A room with this slug already exists.'],
        ];

        foreach ($cases as [$roomNumber, $slug, $message]) {
            try {
                $this->repository->create(
                    $this->roomTypeIds['deluxe'],
                    $roomNumber,
                    'Duplicate Room',
                    $slug,
                    null,
                    '5000.00',
                    1,
                    0,
                    Room::STATUS_ACTIVE,
                    false,
                );
                self::fail('Expected a safe room uniqueness conflict.');
            } catch (RoomCatalogException $exception) {
                self::assertTrue($exception->isConflict());
                self::assertSame($message, $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }
    }

    public function testInvalidAmenityReplacementIsRejectedWithoutMutation(): void
    {
        $before = $this->roomSnapshot();

        foreach ([[0], [1, 1], ['1']] as $amenityIds) {
            try {
                /** @var list<int> $amenityIds */
                $this->repository->replaceAmenities($this->roomIds['public'], $amenityIds);
                self::fail('Expected an invalid room amenity update failure.');
            } catch (DatabaseException $exception) {
                self::assertSame('The room amenity update failed.', $exception->getMessage());
            }
        }

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

    private function insertAmenity(string $name, ?string $icon, bool $active): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO amenities (name, icon, is_active) VALUES (:name, :icon, :is_active)',
        );
        $statement->execute([
            'name' => $name,
            'icon' => $icon,
            'is_active' => $active ? 1 : 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function assignAmenity(int $roomId, int $amenityId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_amenities (room_id, amenity_id) VALUES (:room_id, :amenity_id)',
        );
        $statement->execute(['room_id' => $roomId, 'amenity_id' => $amenityId]);
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
        $amenities = $this->connection->query('SELECT * FROM amenities ORDER BY id')->fetchAll();
        $assignments = $this->connection->query(
            'SELECT * FROM room_amenities ORDER BY room_id, amenity_id',
        )->fetchAll();

        return json_encode([
            'room_types' => $roomTypes,
            'rooms' => $rooms,
            'amenities' => $amenities,
            'room_amenities' => $assignments,
        ], JSON_THROW_ON_ERROR);
    }
}
