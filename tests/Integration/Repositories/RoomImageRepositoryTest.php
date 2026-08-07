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
        $this->roomIds['empty'] = $this->insertRoom($roomTypeId, '203', 'empty-image-room');

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

    public function testItFindsPrimaryImagesForManyRoomsInOneKeyedResult(): void
    {
        $images = $this->repository->findPrimaryByRoomIds([
            $this->roomIds['without_primary'],
            $this->roomIds['with_images'],
            $this->roomIds['with_images'],
            0,
        ]);

        self::assertSame([$this->roomIds['with_images']], array_keys($images));
        self::assertSame($this->imageIds['primary'], $images[$this->roomIds['with_images']]->id());
        self::assertSame([], $this->repository->findPrimaryByRoomIds([0, -1]));
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

    public function testItCountsZeroOneAndMultipleImagesAndRejectsNoIdentifiers(): void
    {
        self::assertSame(0, $this->repository->countByRoomId($this->roomIds['empty']));
        self::assertSame(1, $this->repository->countByRoomId($this->roomIds['without_primary']));
        self::assertSame(3, $this->repository->countByRoomId($this->roomIds['with_images']));
        self::assertSame(0, $this->repository->countByRoomId(0));
        self::assertSame(0, $this->repository->countByRoomId(-1));
    }

    public function testItFindsOnlyAnImageOwnedByTheSuppliedRoom(): void
    {
        $image = $this->repository->findByIdAndRoomId(
            $this->imageIds['primary'],
            $this->roomIds['with_images'],
        );

        self::assertInstanceOf(RoomImage::class, $image);
        self::assertSame($this->imageIds['primary'], $image->id());
        self::assertSame($this->roomIds['with_images'], $image->roomId());
        self::assertSame('rooms/201/primary.webp', $image->filePath());
        self::assertSame('Garden room with natural light', $image->altText());
        self::assertSame(1, $image->sortOrder());
        self::assertTrue($image->isPrimary());
        self::assertNull($this->repository->findByIdAndRoomId(
            $this->imageIds['primary'],
            $this->roomIds['without_primary'],
        ));
        self::assertNull($this->repository->findByIdAndRoomId(999999, $this->roomIds['with_images']));
        self::assertNull($this->repository->findByIdAndRoomId(0, $this->roomIds['with_images']));
        self::assertNull($this->repository->findByIdAndRoomId($this->imageIds['primary'], -1));
    }

    public function testItCreatesAndReloadsCompleteImageMetadata(): void
    {
        $first = $this->repository->create(
            $this->roomIds['empty'],
            'rooms/203/window.png',
            'Window view',
            4,
            false,
        );
        $primary = $this->repository->create(
            $this->roomIds['empty'],
            'rooms/203/primary.webp',
            null,
            0,
            true,
        );

        self::assertGreaterThan(0, $first->id());
        self::assertSame($this->roomIds['empty'], $first->roomId());
        self::assertSame('rooms/203/window.png', $first->filePath());
        self::assertSame('Window view', $first->altText());
        self::assertSame(4, $first->sortOrder());
        self::assertFalse($first->isPrimary());
        self::assertSame('UTC', $first->createdAt()->getTimezone()->getName());
        self::assertGreaterThan(0, $first->createdAt()->getTimestamp());
        self::assertNull($primary->altText());
        self::assertTrue($primary->isPrimary());
        self::assertSame(2, $this->repository->countByRoomId($this->roomIds['empty']));
    }

    public function testCreateConstraintFailuresAreSanitized(): void
    {
        $cases = [
            [
                $this->roomIds['empty'],
                'rooms/201/primary.webp',
                false,
            ],
            [
                $this->roomIds['with_images'],
                'rooms/201/second-primary.webp',
                true,
            ],
            [
                999999,
                'rooms/missing/foreign-key.webp',
                false,
            ],
        ];

        foreach ($cases as [$roomId, $path, $primary]) {
            try {
                $this->repository->create($roomId, $path, null, 0, $primary);
                self::fail('Expected a sanitized room image write failure.');
            } catch (DatabaseException $exception) {
                self::assertSame('The room image write failed.', $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
                self::assertStringNotContainsString($path, $exception->getMessage());
            }
        }
    }

    public function testCreateRejectsInvalidInputsBeforeMutation(): void
    {
        $before = $this->imageSnapshot();
        $paths = [
            '',
            '/absolute.png',
            '\\absolute.png',
            'rooms\\203\\backslash.png',
            'rooms//203/double.png',
            "rooms/203/nul\0.png",
            'rooms/./203/dot.png',
            'rooms/../203/traversal.png',
            "rooms/203/invalid-\xC3\x28.webp",
            str_repeat('a', 256),
        ];

        foreach ($paths as $path) {
            try {
                $this->repository->create($this->roomIds['empty'], $path, null, 0, false);
                self::fail('Expected invalid room image path rejection.');
            } catch (DatabaseException $exception) {
                self::assertSame('The room image write failed.', $exception->getMessage());

                if ($path !== '') {
                    self::assertStringNotContainsString($path, $exception->getMessage());
                }
            }
        }

        foreach ([[0, 0], [-1, 0], [$this->roomIds['empty'], -1]] as [$roomId, $sortOrder]) {
            try {
                $this->repository->create($roomId, 'rooms/203/invalid.webp', null, $sortOrder, false);
                self::fail('Expected invalid room image metadata rejection.');
            } catch (DatabaseException $exception) {
                self::assertSame('The room image write failed.', $exception->getMessage());
            }
        }

        self::assertSame($before, $this->imageSnapshot());
    }

    public function testItUpdatesMetadataToValuesAndNullAndReturnsNoOpModel(): void
    {
        $updated = $this->repository->updateMetadata(
            $this->imageIds['gallery_first'],
            $this->roomIds['with_images'],
            'Updated view',
            8,
        );
        self::assertInstanceOf(RoomImage::class, $updated);
        self::assertSame('Updated view', $updated->altText());
        self::assertSame(8, $updated->sortOrder());

        $nullAlt = $this->repository->updateMetadata(
            $this->imageIds['gallery_first'],
            $this->roomIds['with_images'],
            null,
            8,
        );
        self::assertInstanceOf(RoomImage::class, $nullAlt);
        self::assertNull($nullAlt->altText());

        $noOp = $this->repository->updateMetadata(
            $this->imageIds['gallery_first'],
            $this->roomIds['with_images'],
            null,
            8,
        );
        self::assertInstanceOf(RoomImage::class, $noOp);
        self::assertSame(8, $noOp->sortOrder());
    }

    public function testMetadataUpdateRejectsWrongMissingAndInvalidTargetsWithoutMutation(): void
    {
        $before = $this->imageSnapshot();

        self::assertNull($this->repository->updateMetadata(
            $this->imageIds['primary'],
            $this->roomIds['without_primary'],
            'Wrong room',
            9,
        ));
        self::assertNull($this->repository->updateMetadata(999999, $this->roomIds['with_images'], null, 0));
        self::assertNull($this->repository->updateMetadata(0, $this->roomIds['with_images'], null, 0));

        try {
            $this->repository->updateMetadata(
                $this->imageIds['primary'],
                $this->roomIds['with_images'],
                null,
                -1,
            );
            self::fail('Expected invalid metadata update rejection.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room image metadata update failed.', $exception->getMessage());
        }

        self::assertSame($before, $this->imageSnapshot());
    }

    public function testItClearsAndSetsPrimaryWithOwnedNoOpAndMissingBehavior(): void
    {
        $this->repository->clearPrimaryByRoomId($this->roomIds['with_images']);
        self::assertNull($this->repository->findPrimaryByRoomId($this->roomIds['with_images']));
        $this->repository->clearPrimaryByRoomId($this->roomIds['empty']);

        $set = $this->repository->setPrimary(
            $this->imageIds['gallery_first'],
            $this->roomIds['with_images'],
        );
        self::assertInstanceOf(RoomImage::class, $set);
        self::assertTrue($set->isPrimary());

        $alreadyPrimary = $this->repository->setPrimary(
            $this->imageIds['gallery_first'],
            $this->roomIds['with_images'],
        );
        self::assertInstanceOf(RoomImage::class, $alreadyPrimary);
        self::assertTrue($alreadyPrimary->isPrimary());
        self::assertNull($this->repository->setPrimary(
            $this->imageIds['gallery_first'],
            $this->roomIds['without_primary'],
        ));
        self::assertNull($this->repository->setPrimary(999999, $this->roomIds['with_images']));
        self::assertNull($this->repository->setPrimary(0, $this->roomIds['with_images']));
    }

    public function testPrimaryFailuresAreSanitized(): void
    {
        try {
            $this->repository->clearPrimaryByRoomId(0);
            self::fail('Expected invalid primary clear rejection.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room image primary update failed.', $exception->getMessage());
        }

        try {
            $this->repository->setPrimary(
                $this->imageIds['gallery_first'],
                $this->roomIds['with_images'],
            );
            self::fail('Expected duplicate primary rejection.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room image primary update failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function testItDeletesOnlyOwnedImagesAndReturnsThePreDeleteModel(): void
    {
        $before = $this->repository->findById($this->imageIds['gallery_first']);
        self::assertInstanceOf(RoomImage::class, $before);
        self::assertNull($this->repository->delete(
            $this->imageIds['gallery_first'],
            $this->roomIds['without_primary'],
        ));
        self::assertInstanceOf(RoomImage::class, $this->repository->findById($this->imageIds['gallery_first']));

        $deleted = $this->repository->delete(
            $this->imageIds['gallery_first'],
            $this->roomIds['with_images'],
        );
        self::assertInstanceOf(RoomImage::class, $deleted);
        self::assertSame($before->id(), $deleted->id());
        self::assertSame($before->filePath(), $deleted->filePath());
        self::assertNull($this->repository->findById($this->imageIds['gallery_first']));
        self::assertNull($this->repository->delete(999999, $this->roomIds['with_images']));
        self::assertNull($this->repository->delete(0, $this->roomIds['with_images']));
    }

    public function testCreateUpdatePrimaryAndDeleteChangesRollBack(): void
    {
        $this->connection->beginTransaction();

        try {
            $created = $this->repository->create(
                $this->roomIds['empty'],
                'rooms/203/rollback.webp',
                null,
                0,
                false,
            );
            $this->repository->updateMetadata(
                $this->imageIds['gallery_first'],
                $this->roomIds['with_images'],
                'Rolled back',
                99,
            );
            $this->repository->clearPrimaryByRoomId($this->roomIds['with_images']);
            $this->repository->setPrimary(
                $this->imageIds['gallery_first'],
                $this->roomIds['with_images'],
            );
            $this->repository->delete(
                $this->imageIds['gallery_tie'],
                $this->roomIds['with_images'],
            );
            self::assertInstanceOf(RoomImage::class, $this->repository->findById($created->id()));
        } finally {
            $this->connection->rollBack();
        }

        self::assertSame(0, $this->repository->countByRoomId($this->roomIds['empty']));
        $original = $this->repository->findById($this->imageIds['gallery_first']);
        self::assertInstanceOf(RoomImage::class, $original);
        self::assertNull($original->altText());
        self::assertSame(0, $original->sortOrder());
        self::assertFalse($original->isPrimary());
        self::assertSame(
            $this->imageIds['primary'],
            $this->repository->findPrimaryByRoomId($this->roomIds['with_images'])?->id(),
        );
        self::assertInstanceOf(RoomImage::class, $this->repository->findById($this->imageIds['gallery_tie']));
    }

    public function testListingRemainsDeterministicAfterWrites(): void
    {
        $later = $this->repository->create(
            $this->roomIds['empty'],
            'rooms/203/later.webp',
            null,
            2,
            false,
        );
        $earlier = $this->repository->create(
            $this->roomIds['empty'],
            'rooms/203/earlier.webp',
            null,
            1,
            false,
        );
        $tie = $this->repository->create(
            $this->roomIds['empty'],
            'rooms/203/tie.webp',
            null,
            1,
            false,
        );

        self::assertSame(
            [$earlier->id(), $tie->id(), $later->id()],
            array_map(
                static fn (RoomImage $image): int => $image->id(),
                $this->repository->findAllByRoomId($this->roomIds['empty']),
            ),
        );
    }

    public function testWriteOperationDatabaseFailuresAreSanitized(): void
    {
        $this->dropTables(['room_images']);

        $operations = [
            ['The room image count failed.', fn (): int => $this->repository->countByRoomId(1)],
            ['The room image lookup failed.', fn (): ?RoomImage => $this->repository->findByIdAndRoomId(1, 1)],
            [
                'The room image write failed.',
                fn (): RoomImage => $this->repository->create(1, 'rooms/1/image.webp', null, 0, false),
            ],
            [
                'The room image metadata update failed.',
                fn (): ?RoomImage => $this->repository->updateMetadata(1, 1, null, 0),
            ],
            [
                'The room image primary update failed.',
                fn (): ?RoomImage => $this->repository->setPrimary(1, 1),
            ],
            [
                'The room image primary update failed.',
                function (): void {
                    $this->repository->clearPrimaryByRoomId(1);
                },
            ],
            ['The room image deletion failed.', fn (): ?RoomImage => $this->repository->delete(1, 1)],
        ];

        foreach ($operations as [$message, $operation]) {
            try {
                $operation();
                self::fail('Expected a sanitized database failure.');
            } catch (DatabaseException $exception) {
                self::assertSame($message, $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }
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
