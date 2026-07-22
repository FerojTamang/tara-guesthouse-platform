<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Exceptions\RoomCatalogException;
use App\Models\RoomType;
use App\Repositories\RoomTypeRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class RoomTypeRepositoryTest extends DatabaseTestCase
{
    private RoomTypeRepository $repository;

    /** @var array<string, int> */
    private array $roomTypeIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 3);
        (new MigrationRunner(
            $this->connection,
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $this->roomTypeIds['budget'] = $this->insertRoomType(
            'Budget Room',
            'budget-room',
            null,
            '2500.00',
            2,
            0,
            false,
            '2026-07-22 01:02:03',
            '2026-07-22 02:03:04',
        );
        $this->roomTypeIds['deluxe'] = $this->insertRoomType(
            'Deluxe Suite',
            'Deluxe-Suite',
            'Upper-floor room type fixture.',
            '6500.00',
            2,
            1,
            true,
            '2026-07-22 03:04:05',
            '2026-07-22 04:05:06',
        );
        $this->roomTypeIds['family'] = $this->insertRoomType(
            'Family Room',
            'family-room',
            'Family room type fixture.',
            '8500.00',
            4,
            2,
            true,
            '2026-07-22 05:06:07',
            '2026-07-22 06:07:08',
        );
        $this->repository = new RoomTypeRepository($this->connection);
    }

    public function testItFindsByIdAndMapsTheCompleteRoomTypeRecord(): void
    {
        $roomType = $this->repository->findById($this->roomTypeIds['deluxe']);

        self::assertInstanceOf(RoomType::class, $roomType);
        self::assertSame($this->roomTypeIds['deluxe'], $roomType->id());
        self::assertSame('Deluxe Suite', $roomType->name());
        self::assertSame('Deluxe-Suite', $roomType->slug());
        self::assertSame('Upper-floor room type fixture.', $roomType->description());
        self::assertSame('6500.00', $roomType->basePrice());
        self::assertSame(2, $roomType->capacityAdults());
        self::assertSame(1, $roomType->capacityChildren());
        self::assertTrue($roomType->isActive());
        self::assertEquals(new DateTimeImmutable('2026-07-22 03:04:05 UTC'), $roomType->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-07-22 04:05:06 UTC'), $roomType->updatedAt());
        self::assertSame('UTC', $roomType->createdAt()->getTimezone()->getName());
        self::assertSame('UTC', $roomType->updatedAt()->getTimezone()->getName());
    }

    public function testSlugLookupUsesTheApprovedDatabaseCollationWithoutNormalization(): void
    {
        $roomType = $this->repository->findBySlug('deluxe-suite');

        self::assertInstanceOf(RoomType::class, $roomType);
        self::assertSame($this->roomTypeIds['deluxe'], $roomType->id());
        self::assertSame('Deluxe-Suite', $roomType->slug());
    }

    public function testItListsAllAndActiveRoomTypesInDeterministicNameOrder(): void
    {
        $all = $this->repository->findAll();
        $active = $this->repository->findAllActive();

        self::assertSame(['Budget Room', 'Deluxe Suite', 'Family Room'], array_map(
            static fn (RoomType $roomType): string => $roomType->name(),
            $all,
        ));
        self::assertSame([false, true, true], array_map(
            static fn (RoomType $roomType): bool => $roomType->isActive(),
            $all,
        ));
        self::assertSame(['Deluxe Suite', 'Family Room'], array_map(
            static fn (RoomType $roomType): string => $roomType->name(),
            $active,
        ));
        self::assertNull($all[0]->description());
        self::assertSame('2500.00', $all[0]->basePrice());
    }

    public function testMissingAndInjectionShapedLookupsReturnNullWithoutMutation(): void
    {
        $before = $this->roomTypeSnapshot();

        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(999999));
        self::assertNull($this->repository->findBySlug(''));
        self::assertNull($this->repository->findBySlug("x' OR 1=1 --"));

        self::assertSame($before, $this->roomTypeSnapshot());
    }

    public function testDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_amenities', 'room_images', 'rooms', 'room_types']);

        try {
            $this->repository->findAll();
            self::fail('Expected a sanitized room type listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room type listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function testItCreatesAndReturnsTheStoredRoomType(): void
    {
        $roomType = $this->repository->create(
            'Garden Room',
            'garden-room',
            "Quiet garden-facing room.\nIncludes breakfast.",
            '4200.50',
            3,
            1,
            true,
        );

        self::assertGreaterThan(0, $roomType->id());
        self::assertSame('Garden Room', $roomType->name());
        self::assertSame('garden-room', $roomType->slug());
        self::assertSame("Quiet garden-facing room.\nIncludes breakfast.", $roomType->description());
        self::assertSame('4200.50', $roomType->basePrice());
        self::assertSame(3, $roomType->capacityAdults());
        self::assertSame(1, $roomType->capacityChildren());
        self::assertTrue($roomType->isActive());
        self::assertSame('UTC', $roomType->createdAt()->getTimezone()->getName());
        self::assertSame('UTC', $roomType->updatedAt()->getTimezone()->getName());
        self::assertEquals($roomType, $this->repository->findById($roomType->id()));
    }

    public function testItUpdatesAndReturnsAnExistingRoomTypeIncludingAnUnchangedWrite(): void
    {
        $roomType = $this->repository->update(
            $this->roomTypeIds['deluxe'],
            'Deluxe King Room',
            'deluxe-king-room',
            null,
            '7000.25',
            3,
            0,
            false,
        );

        self::assertInstanceOf(RoomType::class, $roomType);
        self::assertSame($this->roomTypeIds['deluxe'], $roomType->id());
        self::assertSame('Deluxe King Room', $roomType->name());
        self::assertSame('deluxe-king-room', $roomType->slug());
        self::assertNull($roomType->description());
        self::assertSame('7000.25', $roomType->basePrice());
        self::assertSame(3, $roomType->capacityAdults());
        self::assertSame(0, $roomType->capacityChildren());
        self::assertFalse($roomType->isActive());

        $unchanged = $this->repository->update(
            $roomType->id(),
            $roomType->name(),
            $roomType->slug(),
            $roomType->description(),
            $roomType->basePrice(),
            $roomType->capacityAdults(),
            $roomType->capacityChildren(),
            $roomType->isActive(),
        );

        self::assertEquals($roomType, $unchanged);
    }

    public function testMissingWriteTargetsReturnNullWithoutMutation(): void
    {
        $before = $this->roomTypeSnapshot();

        self::assertNull($this->repository->update(0, 'Missing', 'missing', null, '1.00', 1, 0, true));
        self::assertNull($this->repository->update(999999, 'Missing', 'missing', null, '1.00', 1, 0, true));
        self::assertNull($this->repository->setActive(-1, false));
        self::assertNull($this->repository->setActive(999999, false));

        self::assertSame($before, $this->roomTypeSnapshot());
    }

    public function testItChangesOnlyTheRoomTypeActiveState(): void
    {
        $before = $this->repository->findById($this->roomTypeIds['family']);
        self::assertInstanceOf(RoomType::class, $before);

        $inactive = $this->repository->setActive($before->id(), false);

        self::assertInstanceOf(RoomType::class, $inactive);
        self::assertFalse($inactive->isActive());
        self::assertSame($before->name(), $inactive->name());
        self::assertSame($before->slug(), $inactive->slug());
        self::assertSame($before->description(), $inactive->description());
        self::assertSame($before->basePrice(), $inactive->basePrice());
        self::assertSame($before->capacityAdults(), $inactive->capacityAdults());
        self::assertSame($before->capacityChildren(), $inactive->capacityChildren());

        $active = $this->repository->setActive($before->id(), true);
        self::assertInstanceOf(RoomType::class, $active);
        self::assertTrue($active->isActive());
    }

    public function testDuplicateSlugsAreReportedAsSafeConflictsWithoutPartialWrites(): void
    {
        $before = $this->roomTypeSnapshot();

        try {
            $this->repository->create('Duplicate', 'deluxe-suite', null, '3000.00', 1, 0, true);
            self::fail('Expected a duplicate room-type slug conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('A room type with this slug already exists.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->update(
                $this->roomTypeIds['family'],
                'Family Room',
                'budget-room',
                'Family room type fixture.',
                '8500.00',
                4,
                2,
                true,
            );
            self::fail('Expected an update slug conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('A room type with this slug already exists.', $exception->getMessage());
        }

        self::assertSame($before, $this->roomTypeSnapshot());
    }

    public function testWriteDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_amenities', 'room_images', 'rooms', 'room_types']);

        try {
            $this->repository->create('Garden Room', 'garden-room', null, '4200.00', 2, 0, true);
            self::fail('Expected a sanitized room type write failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room type write failed.', $exception->getMessage());
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

    /** @return list<array<string, mixed>> */
    private function roomTypeSnapshot(): array
    {
        $rows = $this->connection->query(
            'SELECT id, name, slug, description, base_price, capacity_adults, capacity_children, is_active, '
            . 'created_at, updated_at FROM room_types ORDER BY id',
        )->fetchAll();

        self::assertIsArray($rows);

        return $rows;
    }
}
