<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
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
