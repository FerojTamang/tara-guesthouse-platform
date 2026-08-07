<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Core\UploadedFile;
use App\Exceptions\RoomImageException;
use App\Models\Room;
use App\Models\RoomImage;
use App\Repositories\RoomImageCatalogRepository;
use App\Repositories\RoomImageRepository;
use App\Repositories\RoomRepository;
use App\Requests\RoomImageUploadRequest;
use App\Services\PdoRoomCatalogTransaction;
use App\Services\RoomImageService;
use App\Storage\LocalRoomImageStorage;
use App\Storage\RoomImageStorage;
use App\Storage\StoredRoomImage;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class RoomImageServiceTest extends DatabaseTestCase
{
    private string $storageRoot;
    private string $uploadPath;
    private int $roomId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner($this->connection, dirname(__DIR__, 3) . '/database/migrations'))->migrate();
        $this->storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-image-service-' . bin2hex(random_bytes(8));
        mkdir($this->storageRoot, 0700);
        $this->uploadPath = tempnam(sys_get_temp_dir(), 'tgh-image-upload-');
        file_put_contents($this->uploadPath, self::png());
        $typeId = $this->insertRoomType();
        $this->roomId = $this->insertRoom($typeId);
    }

    protected function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testUploadsPersistFilesPrimaryRulesAndDeterministicOrder(): void
    {
        $this->withExplicitCleanup(function (): void {
        $service = $this->service(new RoomImageRepository($this->connection));
        $first = $service->upload($this->request(false));
        $second = $service->upload($this->request(false));
        $replacement = $service->upload($this->request(true));
        $repository = new RoomImageRepository($this->connection);

        self::assertTrue($first->isPrimary());
        self::assertFalse($second->isPrimary());
        self::assertTrue($replacement->isPrimary());
        self::assertSame([0, 1, 2], array_map(
            static fn (RoomImage $image): int => $image->sortOrder(),
            $repository->findAllByRoomId($this->roomId),
        ));
        self::assertSame($replacement->id(), $repository->findPrimaryByRoomId($this->roomId)?->id());
        self::assertCount(3, $this->storedFiles());
        self::assertSame($replacement->filePath(), $repository->findById($replacement->id())?->filePath());
        });
    }

    public function testNinthCountPermitsTenthAndTenthRejectsEleventhBeforeStorage(): void
    {
        $this->withExplicitCleanup(function (): void {
        for ($index = 0; $index < 9; $index++) {
            $this->insertImage($index, $index === 0);
        }

        $service = $this->service(new RoomImageRepository($this->connection));
        self::assertInstanceOf(RoomImage::class, $service->upload($this->request(false)));
        self::assertCount(1, $this->storedFiles());

        try {
            $service->upload($this->request(false));
            self::fail('Expected maximum image failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isLimit());
            self::assertSame('This room already has the maximum of 10 images.', $exception->getMessage());
            self::assertCount(1, $this->storedFiles());
            self::assertSame(10, (new RoomImageRepository($this->connection))->countByRoomId($this->roomId));
        }
        });
    }

    public function testRepositoryFailureRollsBackPrimaryAndRemovesStoredFile(): void
    {
        $this->withExplicitCleanup(function (): void {
        $this->insertImage(0, true);
        $repository = new RoomImageRepository($this->connection);
        $service = $this->service(new FailingCreateImageRepository($repository));

        try {
            $service->upload($this->request(true));
            self::fail('Expected repository failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room image operation failed.', $exception->getMessage());
            self::assertCount(0, $this->storedFiles());
            self::assertSame(1, $repository->countByRoomId($this->roomId));
            self::assertTrue($repository->findPrimaryByRoomId($this->roomId)?->isPrimary());
            self::assertFalse($this->connection->inTransaction());
        }
        });
    }

    public function testMetadataUpdatesPersistIncludingNullAndWrongRoomChangesNothing(): void
    {
        $this->withExplicitCleanup(function (): void {
        $repository = new RoomImageRepository($this->connection);
        $imageId = $this->insertImage(3, true);
        $service = $this->service($repository);

        $updated = $service->updateMetadata($this->roomId, $imageId, 'Window view', 8);
        self::assertSame('Window view', $updated->altText());
        self::assertSame(8, $repository->findByIdAndRoomId($imageId, $this->roomId)?->sortOrder());
        self::assertNull($service->updateMetadata($this->roomId, $imageId, null, 4)->altText());

        $otherRoom = $this->insertRoom($this->insertRoomType('other'), 'other');

        try {
            $service->updateMetadata($otherRoom, $imageId, 'wrong', 99);
            self::fail('Expected wrong-room image rejection.');
        } catch (RoomImageException $exception) {
            self::assertSame('The requested room image was not found.', $exception->getMessage());
            $persisted = $repository->findByIdAndRoomId($imageId, $this->roomId);
            self::assertNull($persisted?->altText());
            self::assertSame(4, $persisted?->sortOrder());
        }
        });
    }

    public function testPrimaryReplacementAndAlreadyPrimaryRemainStable(): void
    {
        $this->withExplicitCleanup(function (): void {
        $firstId = $this->insertImage(0, true);
        $secondId = $this->insertImage(1, false);
        $repository = new RoomImageRepository($this->connection);
        $service = $this->service($repository);

        $selected = $service->setPrimary($this->roomId, $secondId);
        self::assertTrue($selected->isPrimary());
        self::assertSame($secondId, $repository->findPrimaryByRoomId($this->roomId)?->id());
        self::assertFalse($repository->findByIdAndRoomId($firstId, $this->roomId)?->isPrimary());
        self::assertSame($secondId, $service->setPrimary($this->roomId, $secondId)->id());
        self::assertSame(1, $this->primaryCount());
        });
    }

    public function testDeleteNonPrimaryRemovesMetadataAndFileAfterCommit(): void
    {
        $this->withExplicitCleanup(function (): void {
        $this->insertImage(0, true);
        $deletedId = $this->persistedImage(1, false);
        $repository = new RoomImageRepository($this->connection);
        $observingStorage = new CommitObservingStorage(
            new LocalRoomImageStorage($this->storageRoot),
            $repository,
            $this->roomId,
            $deletedId,
        );
        $service = $this->service($repository, $observingStorage);

        $deleted = $service->delete($this->roomId, $deletedId);

        self::assertSame($deletedId, $deleted->id());
        self::assertNull($repository->findByIdAndRoomId($deletedId, $this->roomId));
        self::assertTrue($observingStorage->metadataWasAbsentAtRemoval);
        self::assertFileDoesNotExist($this->physicalPath($deleted->filePath()));
        self::assertSame(1, $this->primaryCount());
        });
    }

    public function testDeletingPrimarySelectsDeterministicFallbackAndDeletingOnlyLeavesNone(): void
    {
        $this->withExplicitCleanup(function (): void {
        $primaryId = $this->persistedImage(0, true);
        $fallbackId = $this->insertImage(1, false);
        $this->insertImage(1, false);
        $repository = new RoomImageRepository($this->connection);
        $service = $this->service($repository);

        $service->delete($this->roomId, $primaryId);
        self::assertSame($fallbackId, $repository->findPrimaryByRoomId($this->roomId)?->id());
        self::assertSame(1, $this->primaryCount());

        foreach ($repository->findAllByRoomId($this->roomId) as $image) {
            if ($image->id() !== $fallbackId) {
                $service->delete($this->roomId, $image->id());
            }
        }
        $this->createPhysicalFile($repository->findByIdAndRoomId($fallbackId, $this->roomId)?->filePath() ?? '');
        $service->delete($this->roomId, $fallbackId);
        self::assertSame([], $repository->findAllByRoomId($this->roomId));
        self::assertNull($repository->findPrimaryByRoomId($this->roomId));
        });
    }

    public function testDeleteFailureRollsBackAndPostCommitRemovalFailureLeavesOrphan(): void
    {
        $this->withExplicitCleanup(function (): void {
        $imageId = $this->persistedImage(0, true);
        $repository = new RoomImageRepository($this->connection);
        $filePath = $repository->findByIdAndRoomId($imageId, $this->roomId)?->filePath() ?? '';
        $failingDelete = new WorkflowFailingImageRepository($repository, 'delete');

        try {
            $this->service($failingDelete)->delete($this->roomId, $imageId);
            self::fail('Expected deletion failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertNotNull($repository->findByIdAndRoomId($imageId, $this->roomId));
            self::assertFileExists($this->physicalPath($repository->findByIdAndRoomId($imageId, $this->roomId)?->filePath() ?? ''));
            self::assertTrue($repository->findByIdAndRoomId($imageId, $this->roomId)?->isPrimary());
        }

        $failingStorage = new FailingRemovalStorage(new LocalRoomImageStorage($this->storageRoot));

        try {
            $this->service($repository, $failingStorage)->delete($this->roomId, $imageId);
            self::fail('Expected cleanup failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isCleanupFailure());
            self::assertSame('Room image cleanup failed.', $exception->getMessage());
            self::assertNull($repository->findByIdAndRoomId($imageId, $this->roomId));
            self::assertFileExists($this->physicalPath($filePath));
            self::assertFalse($this->connection->inTransaction());
        }
        });
    }

    public function testFallbackFailureRollsBackDeletionAndPreservesOriginalPrimaryAndFile(): void
    {
        $this->withExplicitCleanup(function (): void {
        $primaryId = $this->persistedImage(0, true);
        $this->insertImage(1, false);
        $repository = new RoomImageRepository($this->connection);
        $path = $repository->findByIdAndRoomId($primaryId, $this->roomId)?->filePath() ?? '';

        try {
            $this->service(new WorkflowFailingImageRepository($repository, 'set'))
                ->delete($this->roomId, $primaryId);
            self::fail('Expected fallback selection failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertNotNull($repository->findByIdAndRoomId($primaryId, $this->roomId));
            self::assertSame($primaryId, $repository->findPrimaryByRoomId($this->roomId)?->id());
            self::assertSame(1, $this->primaryCount());
            self::assertFileExists($this->physicalPath($path));
            self::assertFalse($this->connection->inTransaction());
        }
        });
    }

    private function withExplicitCleanup(callable $test): void
    {
        try {
            $test();
        } finally {
            $this->cleanupResources();
        }
    }

    private function cleanupResources(): void
    {
        try {
            if (isset($this->connection) && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        } catch (\Throwable) {
            // Defensive cleanup must not replace an originating test failure.
        }

        if (isset($this->uploadPath)) {
            @unlink($this->uploadPath);
        }

        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            foreach (scandir($this->storageRoot) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    @unlink($this->storageRoot . DIRECTORY_SEPARATOR . $entry);
                }
            }

            @rmdir($this->storageRoot);
        }
    }

    private function service(RoomImageCatalogRepository $images, ?RoomImageStorage $storage = null): RoomImageService
    {
        return new RoomImageService(
            new RoomRepository($this->connection),
            $images,
            $storage ?? new LocalRoomImageStorage($this->storageRoot),
            new PdoRoomCatalogTransaction($this->connection),
            10,
        );
    }

    private function request(bool $primary): RoomImageUploadRequest
    {
        $file = new UploadedFile('room.png', 'image/png', $this->uploadPath, UPLOAD_ERR_OK, filesize($this->uploadPath));
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        return RoomImageUploadRequest::fromInput($this->roomId, $file, null, null, $primary, $configuration['room_images']);
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return array_values(array_filter(scandir($this->storageRoot) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
    }

    private function insertRoomType(string $suffix = ''): int
    {
        $statement = $this->connection->prepare('INSERT INTO room_types (name, slug, base_price, capacity_adults, capacity_children, is_active) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([
            'Image Type' . ($suffix === '' ? '' : ' ' . $suffix),
            'image-type' . ($suffix === '' ? '' : '-' . $suffix),
            '100.00',
            2,
            0,
            1,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $typeId, string $suffix = ''): int
    {
        $statement = $this->connection->prepare('INSERT INTO rooms (room_type_id, room_number, name, slug, price_per_night, capacity_adults, capacity_children, operational_status, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([
            $typeId,
            $suffix === '' ? '301' : '302',
            'Image Room' . ($suffix === '' ? '' : ' ' . $suffix),
            'image-room-service' . ($suffix === '' ? '' : '-' . $suffix),
            '100.00',
            2,
            0,
            Room::STATUS_ACTIVE,
            1,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(int $sortOrder, bool $primary): int
    {
        $statement = $this->connection->prepare('INSERT INTO room_images (room_id, file_path, alt_text, sort_order, is_primary) VALUES (?, ?, NULL, ?, ?)');
        $statement->execute([
            $this->roomId,
            'uploads/rooms/' . bin2hex(random_bytes(32)) . '.png',
            $sortOrder,
            $primary ? 1 : 0,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    private function persistedImage(int $sortOrder, bool $primary): int
    {
        $id = $this->insertImage($sortOrder, $primary);
        $path = (new RoomImageRepository($this->connection))->findByIdAndRoomId($id, $this->roomId)?->filePath();
        self::assertNotNull($path);
        $this->createPhysicalFile($path);
        return $id;
    }

    private function createPhysicalFile(string $relativePath): void
    {
        file_put_contents($this->physicalPath($relativePath), 'stored');
    }

    private function physicalPath(string $relativePath): string
    {
        return $this->storageRoot . DIRECTORY_SEPARATOR . basename($relativePath);
    }

    private function primaryCount(): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM room_images WHERE room_id = ? AND is_primary = 1');
        $statement->execute([$this->roomId]);
        return (int) $statement->fetchColumn();
    }

    private static function png(): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $row = "\0" . str_repeat("\0", 640 * 3);
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', 640, 480, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(str_repeat($row, 480), 9))
            . $chunk('IEND', '');
    }
}

final readonly class FailingCreateImageRepository implements RoomImageCatalogRepository
{
    public function __construct(private RoomImageCatalogRepository $inner) {}
    public function countByRoomId(int $roomId): int { return $this->inner->countByRoomId($roomId); }
    public function findAllByRoomId(int $roomId): array { return $this->inner->findAllByRoomId($roomId); }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { return $this->inner->findPrimaryByRoomId($roomId); }
    public function findPrimaryByRoomIds(array $roomIds): array { return $this->inner->findPrimaryByRoomIds($roomIds); }
    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage { return $this->inner->findByIdAndRoomId($imageId, $roomId); }
    public function create(int $roomId, string $filePath, ?string $altText, int $sortOrder, bool $primary): RoomImage { throw new RuntimeException('sensitive database failure'); }
    public function clearPrimaryByRoomId(int $roomId): void { $this->inner->clearPrimaryByRoomId($roomId); }
    public function updateMetadata(int $imageId, int $roomId, ?string $altText, int $sortOrder): ?RoomImage { return $this->inner->updateMetadata($imageId, $roomId, $altText, $sortOrder); }
    public function setPrimary(int $imageId, int $roomId): ?RoomImage { return $this->inner->setPrimary($imageId, $roomId); }
    public function delete(int $imageId, int $roomId): ?RoomImage { return $this->inner->delete($imageId, $roomId); }
}

final readonly class WorkflowFailingImageRepository implements RoomImageCatalogRepository
{
    public function __construct(private RoomImageCatalogRepository $inner, private string $failure) {}
    public function countByRoomId(int $roomId): int { return $this->inner->countByRoomId($roomId); }
    public function findAllByRoomId(int $roomId): array { return $this->inner->findAllByRoomId($roomId); }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { return $this->inner->findPrimaryByRoomId($roomId); }
    public function findPrimaryByRoomIds(array $roomIds): array { return $this->inner->findPrimaryByRoomIds($roomIds); }
    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage { return $this->inner->findByIdAndRoomId($imageId, $roomId); }
    public function create(int $roomId, string $filePath, ?string $altText, int $sortOrder, bool $primary): RoomImage { return $this->inner->create($roomId, $filePath, $altText, $sortOrder, $primary); }
    public function clearPrimaryByRoomId(int $roomId): void { $this->inner->clearPrimaryByRoomId($roomId); }
    public function updateMetadata(int $imageId, int $roomId, ?string $altText, int $sortOrder): ?RoomImage { return $this->inner->updateMetadata($imageId, $roomId, $altText, $sortOrder); }
    public function setPrimary(int $imageId, int $roomId): ?RoomImage
    {
        if ($this->failure === 'set') { throw new RuntimeException('sensitive set failure'); }
        return $this->inner->setPrimary($imageId, $roomId);
    }
    public function delete(int $imageId, int $roomId): ?RoomImage
    {
        if ($this->failure === 'delete') { throw new RuntimeException('sensitive delete failure'); }
        return $this->inner->delete($imageId, $roomId);
    }
}

final class FailingRemovalStorage implements RoomImageStorage
{
    public function __construct(private readonly RoomImageStorage $inner) {}
    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage
    {
        return $this->inner->store($file, $detectedMediaType);
    }
    public function remove(string $relativePath): void
    {
        throw new RuntimeException('sensitive removal path ' . $relativePath);
    }
}

final class CommitObservingStorage implements RoomImageStorage
{
    public bool $metadataWasAbsentAtRemoval = false;

    public function __construct(
        private readonly RoomImageStorage $inner,
        private readonly RoomImageCatalogRepository $images,
        private readonly int $roomId,
        private readonly int $imageId,
    ) {}

    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage
    {
        return $this->inner->store($file, $detectedMediaType);
    }

    public function remove(string $relativePath): void
    {
        $this->metadataWasAbsentAtRemoval = $this->images->findByIdAndRoomId($this->imageId, $this->roomId) === null;
        $this->inner->remove($relativePath);
    }
}
