<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\Room;
use App\Models\RoomImage;
use App\Repositories\RoomImageRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class RoomImageRepositoryTest extends DatabaseTestCase
{
    private RoomImageRepository $repository;

    /** @var array<string, int> */
    private array $roomIds = [];

    /** @var array<string, int> */
    private array $imageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 3);
        (new MigrationRunner(
            $this->connection,
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $roomTypeId = $this->insertRoomType();
        $this->roomIds['with_images'] = $this->insertRoom($roomTypeId, '201', 'image-room');
        $this->roomIds['without_primary'] = $this->insertRoom($roomTypeId, '202', 'secondary-image-room');

        $this->imageIds['gallery_first'] = $this->insertImage(
            $this->roomIds['with_images'],
            'rooms/201/gallery-first.webp',
            null,
            0,
            false,
            '2026-07-22 01:02:03',
        );
        $this->imageIds['primary'] = $this->insertImage(
            $this->roomIds['with_images'],
            'rooms/201/primary.webp',
            'Garden room with natural light',
            1,
            true,
            '2026-07-22 02:03:04',
        );
        $this->imageIds['gallery_tie'] = $this->insertImage(
            $this->roomIds['with_images'],
            'rooms/201/gallery-tie.webp',
            'Additional room view',
            1,
            false,
            '2026-07-22 03:04:05',
        );
        $this->imageIds['other_room'] = $this->insertImage(
            $this->roomIds['without_primary'],
            'rooms/202/gallery.webp',
            'Second room view',
            0,
            false,
            '2026-07-22 04:05:06',
        );

        $this->repository = new RoomImageRepository($this->connection);
    }

    public function testItFindsByIdAndMapsTheCompleteRoomImageRecord(): void
    {
        $image = $this->repository->findById($this->imageIds['primary']);

        self::assertInstanceOf(RoomImage::class, $image);
        self::assertSame($this->imageIds['primary'], $image->id());
        self::assertSame($this->roomIds['with_images'], $image->roomId());
        self::assertSame('rooms/201/primary.webp', $image->filePath());
        self::assertSame('Garden room with natural light', $image->altText());
        self::assertSame(1, $image->sortOrder());
        self::assertTrue($image->isPrimary());
        self::assertEquals(new DateTimeImmutable('2026-07-22 02:03:04 UTC'), $image->createdAt());
        self::assertSame('UTC', $image->createdAt()->getTimezone()->getName());
    }

    public function testItListsRoomImagesBySortOrderAndThenIdentifier(): void
    {
        $images = $this->repository->findAllByRoomId($this->roomIds['with_images']);

        self::assertSame(
            [
                $this->imageIds['gallery_first'],
                $this->imageIds['primary'],
                $this->imageIds['gallery_tie'],
            ],
            array_map(static fn (RoomImage $image): int => $image->id(), $images),
        );
        self::assertSame([0, 1, 1], array_map(
            static fn (RoomImage $image): int => $image->sortOrder(),
            $images,
        ));
        self::assertNull($images[0]->altText());
    }

    public function testItFindsTheDeterministicPrimaryImageAndReturnsNullWhenThereIsNone(): void
    {
        $primary = $this->repository->findPrimaryByRoomId($this->roomIds['with_images']);

        self::assertInstanceOf(RoomImage::class, $primary);
        self::assertSame($this->imageIds['primary'], $primary->id());
        self::assertTrue($primary->isPrimary());
        self::assertNull($this->repository->findPrimaryByRoomId($this->roomIds['without_primary']));
    }

    public function testMissingIdentifiersReturnEmptyResultsWithoutMutation(): void
    {
        $before = $this->imageSnapshot();

        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(999999));
        self::assertSame([], $this->repository->findAllByRoomId(-1));
        self::assertSame([], $this->repository->findAllByRoomId(999999));
        self::assertNull($this->repository->findPrimaryByRoomId(0));
        self::assertNull($this->repository->findPrimaryByRoomId(999999));

        self::assertSame($before, $this->imageSnapshot());
    }

    public function testDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_images']);

        try {
            $this->repository->findAllByRoomId($this->roomIds['with_images']);
            self::fail('Expected a sanitized room image listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room image listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertRoomType(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types (name, slug, description, base_price, capacity_adults, capacity_children, '
            . 'is_active) VALUES (:name, :slug, :description, :base_price, :capacity_adults, '
            . ':capacity_children, :is_active)',
        );
        $statement->execute([
            'name' => 'Image Test Room Type',
            'slug' => 'image-test-room-type',
            'description' => null,
            'base_price' => '4000.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'is_active' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $roomTypeId, string $roomNumber, string $slug): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms (room_type_id, room_number, name, slug, description, price_per_night, '
            . 'capacity_adults, capacity_children, operational_status, is_published) '
            . 'VALUES (:room_type_id, :room_number, :name, :slug, :description, :price_per_night, '
            . ':capacity_adults, :capacity_children, :operational_status, :is_published)',
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => $roomNumber,
            'name' => 'Room ' . $roomNumber,
            'slug' => $slug,
            'description' => null,
            'price_per_night' => '4500.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'operational_status' => Room::STATUS_ACTIVE,
            'is_published' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(
        int $roomId,
        string $filePath,
        ?string $altText,
        int $sortOrder,
        bool $primary,
        string $createdAt,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO room_images (room_id, file_path, alt_text, sort_order, is_primary, created_at) '
            . 'VALUES (:room_id, :file_path, :alt_text, :sort_order, :is_primary, :created_at)',
        );
        $statement->execute([
            'room_id' => $roomId,
            'file_path' => $filePath,
            'alt_text' => $altText,
            'sort_order' => $sortOrder,
            'is_primary' => $primary ? 1 : 0,
            'created_at' => $createdAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function imageSnapshot(): string
    {
        $rooms = $this->connection->query('SELECT * FROM rooms ORDER BY id')->fetchAll();
        $images = $this->connection->query('SELECT * FROM room_images ORDER BY id')->fetchAll();

        return json_encode(['rooms' => $rooms, 'room_images' => $images], JSON_THROW_ON_ERROR);
    }
}
