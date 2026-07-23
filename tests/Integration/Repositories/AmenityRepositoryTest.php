<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Repositories\AmenityRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AmenityRepositoryTest extends DatabaseTestCase
{
    private AmenityRepository $repository;

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

        $this->amenityIds['air_conditioning'] = $this->insertAmenity(
            'Air conditioning',
            'snow',
            true,
            '2026-07-22 01:02:03',
            '2026-07-22 02:03:04',
        );
        $this->amenityIds['airport_shuttle'] = $this->insertAmenity(
            'Airport shuttle',
            null,
            false,
            '2026-07-22 03:04:05',
            '2026-07-22 04:05:06',
        );
        $this->amenityIds['wifi'] = $this->insertAmenity(
            'Wi-Fi',
            'wifi',
            true,
            '2026-07-22 05:06:07',
            '2026-07-22 06:07:08',
        );
        $this->repository = new AmenityRepository($this->connection);
    }

    public function testItFindsByIdAndMapsTheCompleteAmenityRecord(): void
    {
        $amenity = $this->repository->findById($this->amenityIds['wifi']);

        self::assertInstanceOf(Amenity::class, $amenity);
        self::assertSame($this->amenityIds['wifi'], $amenity->id());
        self::assertSame('Wi-Fi', $amenity->name());
        self::assertSame('wifi', $amenity->icon());
        self::assertTrue($amenity->isActive());
        self::assertEquals(new DateTimeImmutable('2026-07-22 05:06:07 UTC'), $amenity->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-07-22 06:07:08 UTC'), $amenity->updatedAt());
        self::assertSame('UTC', $amenity->createdAt()->getTimezone()->getName());
        self::assertSame('UTC', $amenity->updatedAt()->getTimezone()->getName());
    }

    public function testItListsAllAndActiveAmenitiesInDeterministicNameOrder(): void
    {
        $all = $this->repository->findAll();
        $active = $this->repository->findAllActive();

        self::assertSame(['Air conditioning', 'Airport shuttle', 'Wi-Fi'], array_map(
            static fn (Amenity $amenity): string => $amenity->name(),
            $all,
        ));
        self::assertSame([true, false, true], array_map(
            static fn (Amenity $amenity): bool => $amenity->isActive(),
            $all,
        ));
        self::assertSame(['Air conditioning', 'Wi-Fi'], array_map(
            static fn (Amenity $amenity): string => $amenity->name(),
            $active,
        ));
        self::assertNull($all[1]->icon());
    }

    public function testMissingIdentifiersReturnNullWithoutMutation(): void
    {
        $before = $this->amenitySnapshot();

        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(-1));
        self::assertNull($this->repository->findById(999999));

        self::assertSame($before, $this->amenitySnapshot());
    }

    public function testDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_amenities', 'amenities']);

        try {
            $this->repository->findById($this->amenityIds['wifi']);
            self::fail('Expected a sanitized amenity lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The amenity lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function testItCreatesAndReturnsTheStoredAmenity(): void
    {
        $amenity = $this->repository->create('Breakfast included', 'cup-hot', true);

        self::assertGreaterThan(0, $amenity->id());
        self::assertSame('Breakfast included', $amenity->name());
        self::assertSame('cup-hot', $amenity->icon());
        self::assertTrue($amenity->isActive());
        self::assertSame('UTC', $amenity->createdAt()->getTimezone()->getName());
        self::assertSame('UTC', $amenity->updatedAt()->getTimezone()->getName());
        self::assertEquals($amenity, $this->repository->findById($amenity->id()));
    }

    public function testItUpdatesAndReturnsAnExistingAmenityIncludingAnUnchangedWrite(): void
    {
        $amenity = $this->repository->update(
            $this->amenityIds['airport_shuttle'],
            'Airport transfer',
            'car-front',
            true,
        );

        self::assertInstanceOf(Amenity::class, $amenity);
        self::assertSame($this->amenityIds['airport_shuttle'], $amenity->id());
        self::assertSame('Airport transfer', $amenity->name());
        self::assertSame('car-front', $amenity->icon());
        self::assertTrue($amenity->isActive());

        $unchanged = $this->repository->update(
            $amenity->id(),
            $amenity->name(),
            $amenity->icon(),
            $amenity->isActive(),
        );

        self::assertEquals($amenity, $unchanged);
    }

    public function testMissingWriteTargetsReturnNullWithoutMutation(): void
    {
        $before = $this->amenitySnapshot();

        self::assertNull($this->repository->update(0, 'Missing', null, true));
        self::assertNull($this->repository->update(999999, 'Missing', null, true));
        self::assertNull($this->repository->setActive(-1, false));
        self::assertNull($this->repository->setActive(999999, false));

        self::assertSame($before, $this->amenitySnapshot());
    }

    public function testItChangesOnlyTheAmenityActiveState(): void
    {
        $before = $this->repository->findById($this->amenityIds['wifi']);
        self::assertInstanceOf(Amenity::class, $before);

        $inactive = $this->repository->setActive($before->id(), false);

        self::assertInstanceOf(Amenity::class, $inactive);
        self::assertFalse($inactive->isActive());
        self::assertSame($before->name(), $inactive->name());
        self::assertSame($before->icon(), $inactive->icon());

        $active = $this->repository->setActive($before->id(), true);
        self::assertInstanceOf(Amenity::class, $active);
        self::assertTrue($active->isActive());
    }

    public function testDuplicateNamesAreReportedAsSafeConflictsWithoutPartialWrites(): void
    {
        $before = $this->amenitySnapshot();

        try {
            $this->repository->create('wi-fi', 'broadcast', true);
            self::fail('Expected a duplicate amenity-name conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('An amenity with this name already exists.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->update(
                $this->amenityIds['airport_shuttle'],
                'Air conditioning',
                'car-front',
                true,
            );
            self::fail('Expected an update name conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('An amenity with this name already exists.', $exception->getMessage());
        }

        self::assertSame($before, $this->amenitySnapshot());
    }

    public function testWriteDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_amenities', 'amenities']);

        try {
            $this->repository->create('Breakfast included', 'cup-hot', true);
            self::fail('Expected a sanitized amenity write failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The amenity write failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertAmenity(
        string $name,
        ?string $icon,
        bool $active,
        string $createdAt,
        string $updatedAt,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO amenities (name, icon, is_active, created_at, updated_at) '
            . 'VALUES (:name, :icon, :is_active, :created_at, :updated_at)',
        );
        $statement->execute([
            'name' => $name,
            'icon' => $icon,
            'is_active' => $active ? 1 : 0,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    private function amenitySnapshot(): array
    {
        $rows = $this->connection->query(
            'SELECT id, name, icon, is_active, created_at, updated_at FROM amenities ORDER BY id',
        )->fetchAll();

        self::assertIsArray($rows);

        return $rows;
    }
}
