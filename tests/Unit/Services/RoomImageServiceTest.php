<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\UploadedFile;
use App\Exceptions\RoomImageException;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Requests\RoomImageUploadRequest;
use App\Services\RoomCatalogTransaction;
use App\Services\RoomImageService;
use App\Storage\RoomImageStorage;
use App\Storage\StoredRoomImage;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoomImageServiceTest extends TestCase
{
    private string $uploadPath;

    protected function setUp(): void
    {
        $this->uploadPath = tempnam(sys_get_temp_dir(), 'tgh-image-service-');
        file_put_contents($this->uploadPath, self::png());
    }

    protected function tearDown(): void
    {
        @unlink($this->uploadPath);
    }

    public function testFirstUploadIsForcedPrimaryAndUsesZeroDefaultOrder(): void
    {
        [$service, $rooms, $images, $storage, $transaction] = $this->fixture();
        $created = $service->upload($this->request(primary: false));

        self::assertTrue($created->isPrimary());
        self::assertSame(0, $created->sortOrder());
        self::assertSame(['begin', 'lock', 'count', 'list', 'store', 'create', 'commit'], $transaction->events);
        self::assertSame(1, $rooms->locks);
        self::assertSame(1, $storage->stores);
        self::assertSame(0, $storage->removes);
        self::assertSame(1, $images->creates);
    }

    public function testSecondUploadDefaultsNonPrimaryAndAppendsWithCap(): void
    {
        $existing = $this->image(1, 7, 65535, true);
        [$service, , $images] = $this->fixture(count: 1, existing: [$existing]);
        $created = $service->upload($this->request(primary: false));

        self::assertFalse($created->isPrimary());
        self::assertSame(65535, $created->sortOrder());
        self::assertSame(0, $images->clears);
    }

    public function testSuppliedOrderAndRequestedPrimaryReplaceInSafeOrder(): void
    {
        $primary = $this->image(1, 7, 2, true);
        [$service, , $images, , $transaction] = $this->fixture(count: 1, existing: [$primary], primary: $primary);
        $created = $service->upload($this->request(sortOrder: 14, primary: true));

        self::assertTrue($created->isPrimary());
        self::assertSame(14, $created->sortOrder());
        self::assertSame(1, $images->clears);
        self::assertSame(
            ['begin', 'lock', 'count', 'primary', 'store', 'clear', 'create', 'commit'],
            $transaction->events,
        );
    }

    public function testRequestedPrimaryWithoutExistingPrimaryCreatesDirectly(): void
    {
        [$service, , $images] = $this->fixture(count: 1, existing: [$this->image(1, 7, 4, false)]);
        self::assertTrue($service->upload($this->request(primary: true))->isPrimary());
        self::assertSame(0, $images->clears);
    }

    public function testNinthCountPermitsUploadAndLimitRejectsBeforeStorage(): void
    {
        [$allowed, , , $allowedStorage] = $this->fixture(count: 9);
        self::assertInstanceOf(RoomImage::class, $allowed->upload($this->request()));
        self::assertSame(1, $allowedStorage->stores);

        [$limited, , , $storage, $transaction] = $this->fixture(count: 10);

        try {
            $limited->upload($this->request());
            self::fail('Expected image limit failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isLimit());
            self::assertSame('This room already has the maximum of 10 images.', $exception->getMessage());
            self::assertSame(0, $storage->stores);
            self::assertSame(['begin', 'lock', 'count', 'rollback'], $transaction->events);
        }
    }

    public function testMissingRoomAndExistingTransactionFailBeforeStorage(): void
    {
        [$missing, , , $missingStorage] = $this->fixture(roomExists: false);

        try {
            $missing->upload($this->request());
            self::fail('Expected missing room failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The requested room was not found.', $exception->getMessage());
            self::assertSame(0, $missingStorage->stores);
        }

        [$nested, , , $nestedStorage, $transaction] = $this->fixture();
        $transaction->active = true;

        try {
            $nested->upload($this->request());
            self::fail('Expected nested transaction rejection.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame(0, $nestedStorage->stores);
            self::assertSame([], $transaction->events);
        }
    }

    public function testStorageFailureRollsBackWithoutRemoval(): void
    {
        [$service, , , $storage, $transaction] = $this->fixture();
        $storage->failStore = true;

        try {
            $service->upload($this->request());
            self::fail('Expected storage failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isStorageFailure());
            self::assertSame('Room image storage failed.', $exception->getMessage());
            self::assertSame(0, $storage->removes);
            self::assertSame('rollback', $transaction->events[array_key_last($transaction->events)]);
        }
    }

    public function testRepositoryFailuresRollBackBeforeRemovingStoredFile(): void
    {
        foreach (['create', 'clear'] as $failure) {
            $primary = $this->image(1, 7, 0, true);
            [$service, , $images, $storage, $transaction] = $this->fixture(
                count: $failure === 'clear' ? 1 : 0,
                existing: $failure === 'clear' ? [$primary] : [],
                primary: $failure === 'clear' ? $primary : null,
            );
            $images->failure = $failure;

            try {
                $service->upload($this->request(primary: $failure === 'clear'));
                self::fail('Expected repository failure.');
            } catch (RoomImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame(1, $storage->removes);
                self::assertLessThan(
                    array_search('remove', $transaction->events, true),
                    array_search('rollback', $transaction->events, true),
                );
            }
        }
    }

    public function testCommitAndRollbackFailureCompensationRules(): void
    {
        [$recoverable, , , $storage, $transaction] = $this->fixture();
        $transaction->failCommitActive = true;

        try {
            $recoverable->upload($this->request());
            self::fail('Expected commit failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame(1, $storage->removes);
        }

        [$ambiguous, , , $ambiguousStorage, $ambiguousTransaction] = $this->fixture();
        $ambiguousTransaction->failCommitAmbiguous = true;

        try {
            $ambiguous->upload($this->request());
            self::fail('Expected ambiguous commit failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame(0, $ambiguousStorage->removes);
        }

        [$rollbackFailure, , $images, $retained, $failedTransaction] = $this->fixture();
        $images->failure = 'create';
        $failedTransaction->failRollback = true;

        try {
            $rollbackFailure->upload($this->request());
            self::fail('Expected rollback cleanup failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isCleanupFailure());
            self::assertSame(0, $retained->removes);
        }
    }

    public function testRemovalFailureProducesSanitizedCleanupFailure(): void
    {
        [$service, , $images, $storage] = $this->fixture();
        $images->failure = 'create';
        $storage->failRemove = true;

        try {
            $service->upload($this->request());
            self::fail('Expected cleanup failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isCleanupFailure());
            self::assertSame('Room image cleanup failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive', $exception->getMessage());
            self::assertNotNull($exception->cleanupCause());
        }
    }

    /** @return array{RoomImageService, ServiceRoomRepository, ServiceImageRepository, ServiceStorage, ServiceTransaction} */
    private function fixture(
        int $count = 0,
        array $existing = [],
        ?RoomImage $primary = null,
        bool $roomExists = true,
    ): array {
        $events = [];
        $transaction = new ServiceTransaction($events);
        $rooms = new ServiceRoomRepository($events, $roomExists ? $this->room() : null);
        $images = new ServiceImageRepository($events, $count, $existing, $primary);
        $storage = new ServiceStorage($events);

        return [new RoomImageService($rooms, $images, $storage, $transaction, 10), $rooms, $images, $storage, $transaction];
    }

    private function request(?int $sortOrder = null, bool $primary = false): RoomImageUploadRequest
    {
        $file = new UploadedFile('room.png', 'image/png', $this->uploadPath, UPLOAD_ERR_OK, filesize($this->uploadPath));

        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';

        return RoomImageUploadRequest::fromInput(
            7,
            $file,
            null,
            $sortOrder,
            $primary,
            $configuration['room_images'],
        );
    }

    private function room(): Room
    {
        $time = new DateTimeImmutable('2026-01-01 UTC');
        $type = new RoomType(1, 'Type', 'type', null, '100.00', 2, 0, true, $time, $time);
        return new Room(7, $type, '7', 'Room', 'room', null, '100.00', 2, 0, Room::STATUS_ACTIVE, true, $time, $time, null);
    }

    private function image(int $id, int $roomId, int $sortOrder, bool $primary): RoomImage
    {
        return new RoomImage($id, $roomId, 'uploads/rooms/' . str_repeat(dechex($id % 16), 64) . '.png', null, $sortOrder, $primary, new DateTimeImmutable('2026-01-01 UTC'));
    }

    private static function png(): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $rows = str_repeat("\0" . str_repeat("\0", 640 * 3), 480);
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', 640, 480, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress($rows, 9))
            . $chunk('IEND', '');
    }
}

final class ServiceRoomRepository implements RoomCatalogRepository
{
    public int $locks = 0;
    public function __construct(private array &$events, private ?Room $room) {}
    public function lockCurrentById(int $roomId): ?Room { $this->events[] = 'lock'; $this->locks++; return $this->room; }
    public function findById(int $id): ?Room { return $this->room; }
    public function findAll(): array { return []; }
    public function findAmenityIds(int $roomId): array { return []; }
    public function create(int $roomTypeId, string $roomNumber, string $name, string $slug, ?string $description, string $pricePerNight, int $capacityAdults, int $capacityChildren, string $operationalStatus, bool $published): Room { throw new LogicException(); }
    public function update(int $id, int $roomTypeId, string $roomNumber, string $name, string $slug, ?string $description, string $pricePerNight, int $capacityAdults, int $capacityChildren, string $operationalStatus, bool $published): ?Room { throw new LogicException(); }
    public function replaceAmenities(int $roomId, array $amenityIds): void { throw new LogicException(); }
    public function softDelete(int $id): ?Room { throw new LogicException(); }
}

final class ServiceImageRepository implements RoomImageCatalogRepository
{
    public int $creates = 0;
    public int $clears = 0;
    public ?string $failure = null;
    public function __construct(private array &$events, private int $count, private array $existing, private ?RoomImage $primary) {}
    public function countByRoomId(int $roomId): int { $this->events[] = 'count'; return $this->count; }
    public function findAllByRoomId(int $roomId): array { $this->events[] = 'list'; return $this->existing; }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { $this->events[] = 'primary'; return $this->primary; }
    public function create(int $roomId, string $filePath, ?string $altText, int $sortOrder, bool $primary): RoomImage
    {
        $this->events[] = 'create'; $this->creates++;
        if ($this->failure === 'create') { throw new RuntimeException('sensitive duplicate path'); }
        return new RoomImage(99, $roomId, $filePath, $altText, $sortOrder, $primary, new DateTimeImmutable('2026-01-01 UTC'));
    }
    public function clearPrimaryByRoomId(int $roomId): void
    {
        $this->events[] = 'clear'; $this->clears++;
        if ($this->failure === 'clear') { throw new RuntimeException('sensitive primary failure'); }
    }
}

final class ServiceStorage implements RoomImageStorage
{
    public int $stores = 0;
    public int $removes = 0;
    public bool $failStore = false;
    public bool $failRemove = false;
    public function __construct(private array &$events) {}
    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage
    {
        $this->events[] = 'store'; $this->stores++;
        if ($this->failStore) { throw new RuntimeException('sensitive storage path'); }
        return new StoredRoomImage('uploads/rooms/' . str_repeat('a', 64) . '.png', str_repeat('a', 64) . '.png', 'png', $file->size());
    }
    public function remove(string $relativePath): void
    {
        $this->events[] = 'remove'; $this->removes++;
        if ($this->failRemove) { throw new RuntimeException('sensitive cleanup path'); }
    }
}

final class ServiceTransaction implements RoomCatalogTransaction
{
    public bool $active = false;
    public bool $failCommitActive = false;
    public bool $failCommitAmbiguous = false;
    public bool $failRollback = false;
    public function __construct(public array &$events) {}
    public function begin(): void { $this->events[] = 'begin'; $this->active = true; }
    public function commit(): void
    {
        $this->events[] = 'commit';
        if ($this->failCommitAmbiguous) { $this->active = false; throw new RuntimeException('ambiguous'); }
        if ($this->failCommitActive) { throw new RuntimeException('commit'); }
        $this->active = false;
    }
    public function rollBack(): void
    {
        $this->events[] = 'rollback';
        if ($this->failRollback) { throw new RuntimeException('rollback'); }
        $this->active = false;
    }
    public function inProgress(): bool { return $this->active; }
}
