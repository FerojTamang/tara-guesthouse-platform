<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
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
