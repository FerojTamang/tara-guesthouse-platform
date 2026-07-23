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

    public function testOrderedReadUsesRepositoryOrderAndSanitizesFailures(): void
    {
        $second = $this->image(2, 7, 2, true);
        $first = $this->image(1, 7, 9, false);
        [$service, , $images, , $transaction] = $this->fixture(existing: [$second, $first]);

        self::assertSame([$second, $first], $service->listForRoom(7));
        self::assertSame(['list'], $transaction->events);

        $images->failure = 'list';

        try {
            $service->listForRoom(7);
            self::fail('Expected a sanitized image exception.');
        } catch (RoomImageException $exception) {
            self::assertSame('Room image operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }

    public function testOrderedReadRejectsMissingRoomBeforeRepository(): void
    {
        [$service, , , , $transaction] = $this->fixture(roomExists: false);

        try {
            $service->listForRoom(7);
            self::fail('Expected a missing-room exception.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame([], $transaction->events);
        }
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

    public function testMetadataUpdateUsesOwnedImageAndCommitsWithoutStorage(): void
    {
        [$service, , $images, $storage, $transaction] = $this->fixture();
        $images->owned = $this->image(4, 7, 3, false);
        $images->updateResult = $this->image(4, 7, 12, false, 'Courtyard');

        $updated = $service->updateMetadata(7, 4, 'Courtyard', 12);

        self::assertSame('Courtyard', $updated->altText());
        self::assertSame(12, $updated->sortOrder());
        self::assertSame(['begin', 'lock', 'owned', 'update', 'commit'], $transaction->events);
        self::assertSame(0, $storage->stores);
        self::assertSame(0, $storage->removes);
    }

    public function testMetadataUpdateAllowsNullAndRejectsInvalidOrMissingTargets(): void
    {
        [$service, , $images] = $this->fixture();
        $images->owned = $this->image(4, 7, 3, false, 'Old');
        $images->updateResult = $this->image(4, 7, 5, false);
        self::assertNull($service->updateMetadata(7, 4, null, 5)->altText());

        foreach (['room', 'image', 'negative'] as $case) {
            [$failing, , $repository, $storage, $transaction] = $this->fixture(roomExists: $case !== 'room');
            $repository->owned = $case === 'image' ? null : $this->image(4, 7, 3, false);

            try {
                $failing->updateMetadata(7, 4, null, $case === 'negative' ? -1 : 0);
                self::fail('Expected metadata rejection.');
            } catch (RoomImageException $exception) {
                self::assertSame($case === 'room'
                    ? 'The requested room was not found.'
                    : ($case === 'image' ? 'The requested room image was not found.' : 'Room image operation failed.'), $exception->getMessage());
                self::assertSame(0, $storage->stores);
                self::assertSame('rollback', $transaction->events[array_key_last($transaction->events)]);
            }
        }
    }

    public function testMetadataRepositoryNullAndFailuresUseSafePrecedence(): void
    {
        foreach (['null', 'failure', 'commit', 'rollback'] as $case) {
            [$service, , $images, , $transaction] = $this->fixture();
            $images->owned = $this->image(4, 7, 0, false);
            $images->updateResult = $case === 'null' ? null : $this->image(4, 7, 1, false);
            $images->failure = in_array($case, ['failure', 'rollback'], true) ? 'update' : null;
            $transaction->failCommitActive = $case === 'commit';
            $transaction->failRollback = $case === 'rollback';

            try {
                $service->updateMetadata(7, 4, null, 1);
                self::fail('Expected metadata workflow failure.');
            } catch (RoomImageException $exception) {
                self::assertSame(
                    $case === 'rollback' ? 'Room image cleanup failed.' : 'Room image operation failed.',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function testPrimaryReplacementAndAlreadyPrimaryFollowExactOrder(): void
    {
        [$service, , $images, $storage, $transaction] = $this->fixture();
        $images->owned = $this->image(4, 7, 2, false);
        $images->setResult = $this->image(4, 7, 2, true);
        self::assertTrue($service->setPrimary(7, 4)->isPrimary());
        self::assertSame(['begin', 'lock', 'owned', 'clear', 'set', 'commit'], $transaction->events);
        self::assertSame(0, $storage->removes);

        [$stable, , $stableImages, , $stableTransaction] = $this->fixture();
        $stableImages->owned = $this->image(4, 7, 2, true);
        self::assertTrue($stable->setPrimary(7, 4)->isPrimary());
        self::assertSame(['begin', 'lock', 'owned', 'commit'], $stableTransaction->events);
        self::assertSame(0, $stableImages->clears);
        self::assertSame(0, $stableImages->sets);
    }

    public function testPrimaryFailuresRollBackAndRemainSanitized(): void
    {
        foreach (['clear', 'set', 'null'] as $case) {
            [$service, , $images, $storage, $transaction] = $this->fixture();
            $images->owned = $this->image(4, 7, 2, false);
            $images->setResult = $case === 'null' ? null : $this->image(4, 7, 2, true);
            $images->failure = $case === 'null' ? null : $case;

            try {
                $service->setPrimary(7, 4);
                self::fail('Expected primary workflow failure.');
            } catch (RoomImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('rollback', $transaction->events[array_key_last($transaction->events)]);
                self::assertSame(0, $storage->removes);
            }
        }
    }

    public function testPrimaryActiveCommitFailureRollsBackWithoutStorage(): void
    {
        [$service, $rooms, $images, $storage, $transaction] = $this->fixture();
        $images->owned = $this->image(4, 7, 2, false);
        $images->setResult = $this->image(4, 7, 2, true);
        $transaction->failCommitActive = true;

        try {
            $service->setPrimary(7, 4);
            self::fail('Expected active commit failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room image operation failed.', $exception->getMessage());
            self::assertSame(1, $rooms->locks);
            self::assertSame(1, $images->clears);
            self::assertSame(1, $images->sets);
            self::assertSame(
                ['begin', 'lock', 'owned', 'clear', 'set', 'commit', 'rollback'],
                $transaction->events,
            );
            self::assertFalse($transaction->active);
            self::assertSame(0, $storage->stores);
            self::assertSame(0, $storage->removes);
        }
    }

    public function testDeleteNonPrimaryCommitsBeforeRemovingAndReturnsDeletedModel(): void
    {
        [$service, , $images, $storage, $transaction] = $this->fixture();
        $owned = $this->image(4, 7, 2, false);
        $images->owned = $owned;
        $images->deleteResult = $owned;

        self::assertSame($owned, $service->delete(7, 4));
        self::assertSame(['begin', 'lock', 'owned', 'delete', 'commit', 'remove'], $transaction->events);
        self::assertSame([$owned->filePath()], $storage->removedPaths);
        self::assertSame(0, $images->sets);
    }

    public function testDeletePrimarySelectsFirstFallbackOrLeavesNone(): void
    {
        $owned = $this->image(4, 7, 0, true);
        $first = $this->image(5, 7, 1, false);
        $second = $this->image(6, 7, 1, false);
        [$service, , $images, , $transaction] = $this->fixture(existing: [$first, $second]);
        $images->owned = $owned;
        $images->deleteResult = $owned;
        $images->setResult = $this->image(5, 7, 1, true);

        $service->delete(7, 4);
        self::assertSame(['begin', 'lock', 'owned', 'delete', 'list', 'set', 'commit', 'remove'], $transaction->events);
        self::assertSame(1, $images->sets);
        self::assertSame(0, $images->clears);

        [$onlyService, , $onlyImages, , $onlyTransaction] = $this->fixture();
        $onlyImages->owned = $owned;
        $onlyImages->deleteResult = $owned;
        $onlyService->delete(7, 4);
        self::assertSame(['begin', 'lock', 'owned', 'delete', 'list', 'commit', 'remove'], $onlyTransaction->events);
    }

    public function testDeletePreCommitFailuresNeverRemoveFile(): void
    {
        foreach (['delete', 'delete-null', 'fallback'] as $case) {
            $fallback = $this->image(5, 7, 1, false);
            [$service, , $images, $storage, $transaction] = $this->fixture(existing: [$fallback]);
            $images->owned = $this->image(4, 7, 0, true);
            $images->deleteResult = $case === 'delete-null' ? null : $images->owned;
            $images->setResult = $this->image(5, 7, 1, true);
            $images->failure = $case === 'delete' ? 'delete' : ($case === 'fallback' ? 'set' : null);

            try {
                $service->delete(7, 4);
                self::fail('Expected delete workflow failure.');
            } catch (RoomImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame(0, $storage->removes);
                self::assertSame('rollback', $transaction->events[array_key_last($transaction->events)]);
            }
        }
    }

    public function testDeleteCommitRollbackAndPostCommitCleanupPrecedence(): void
    {
        foreach (['commit', 'rollback', 'remove'] as $case) {
            [$service, , $images, $storage, $transaction] = $this->fixture();
            $images->owned = $this->image(4, 7, 0, false);
            $images->deleteResult = $images->owned;
            $transaction->failCommitActive = $case === 'commit' || $case === 'rollback';
            $transaction->failRollback = $case === 'rollback';
            $storage->failRemove = $case === 'remove';

            try {
                $service->delete(7, 4);
                self::fail('Expected deletion failure.');
            } catch (RoomImageException $exception) {
                self::assertSame(
                    in_array($case, ['rollback', 'remove'], true)
                        ? 'Room image cleanup failed.'
                        : 'Room image operation failed.',
                    $exception->getMessage(),
                );
                self::assertSame($case === 'remove' ? 1 : 0, $storage->removes);
                if ($case === 'remove') {
                    self::assertNotContains('rollback', $transaction->events);
                    self::assertNotNull($exception->cleanupCause());
                }
            }
        }
    }

    public function testNewWorkflowsRejectNestedTransactionsWithoutMutation(): void
    {
        foreach (['metadata', 'primary', 'delete'] as $workflow) {
            [$service, , $images, $storage, $transaction] = $this->fixture();
            $transaction->active = true;

            try {
                match ($workflow) {
                    'metadata' => $service->updateMetadata(7, 4, null, 0),
                    'primary' => $service->setPrimary(7, 4),
                    'delete' => $service->delete(7, 4),
                };
                self::fail('Expected nested transaction rejection.');
            } catch (RoomImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame([], $transaction->events);
                self::assertSame(0, $images->updates + $images->sets + $images->deletes);
                self::assertSame(0, $storage->removes);
            }
        }
    }

    public function testPrimaryAndDeleteKeepMissingRoomsAndImagesIndistinguishable(): void
    {
        foreach (['primary', 'delete'] as $workflow) {
            foreach (['room', 'image'] as $missing) {
                [$service, , $images, $storage, $transaction] = $this->fixture(roomExists: $missing !== 'room');
                $images->owned = null;

                try {
                    $workflow === 'primary'
                        ? $service->setPrimary(7, 99)
                        : $service->delete(7, 99);
                    self::fail('Expected missing target rejection.');
                } catch (RoomImageException $exception) {
                    self::assertTrue($exception->isNotFound());
                    self::assertSame(
                        $missing === 'room'
                            ? 'The requested room was not found.'
                            : 'The requested room image was not found.',
                        $exception->getMessage(),
                    );
                    self::assertSame('rollback', $transaction->events[array_key_last($transaction->events)]);
                    self::assertSame(0, $storage->removes);
                }
            }
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

    private function image(int $id, int $roomId, int $sortOrder, bool $primary, ?string $altText = null): RoomImage
    {
        return new RoomImage($id, $roomId, 'uploads/rooms/' . str_repeat(dechex($id % 16), 64) . '.png', $altText, $sortOrder, $primary, new DateTimeImmutable('2026-01-01 UTC'));
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
    public function findAllPublic(): array { return []; }
    public function findPublicBySlug(string $slug): ?Room { return null; }
    public function findActiveAmenitiesByRoomId(int $roomId): array { return []; }
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
    public int $updates = 0;
    public int $sets = 0;
    public int $deletes = 0;
    public ?string $failure = null;
    public ?RoomImage $owned = null;
    public ?RoomImage $updateResult = null;
    public ?RoomImage $setResult = null;
    public ?RoomImage $deleteResult = null;
    public function __construct(private array &$events, private int $count, private array $existing, private ?RoomImage $primary) {}
    public function countByRoomId(int $roomId): int { $this->events[] = 'count'; return $this->count; }
    public function findAllByRoomId(int $roomId): array
    {
        $this->events[] = 'list';
        if ($this->failure === 'list') { throw new RuntimeException('sensitive database path'); }
        return $this->existing;
    }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { $this->events[] = 'primary'; return $this->primary; }
    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage
    {
        $this->events[] = 'owned';
        return $this->owned;
    }
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
    public function updateMetadata(int $imageId, int $roomId, ?string $altText, int $sortOrder): ?RoomImage
    {
        $this->events[] = 'update'; $this->updates++;
        if ($this->failure === 'update') { throw new RuntimeException('sensitive metadata failure'); }
        return $this->updateResult;
    }
    public function setPrimary(int $imageId, int $roomId): ?RoomImage
    {
        $this->events[] = 'set'; $this->sets++;
        if ($this->failure === 'set') { throw new RuntimeException('sensitive primary failure'); }
        return $this->setResult;
    }
    public function delete(int $imageId, int $roomId): ?RoomImage
    {
        $this->events[] = 'delete'; $this->deletes++;
        if ($this->failure === 'delete') { throw new RuntimeException('sensitive delete failure'); }
        return $this->deleteResult;
    }
}

final class ServiceStorage implements RoomImageStorage
{
    public int $stores = 0;
    public int $removes = 0;
    public bool $failStore = false;
    public bool $failRemove = false;
    public array $removedPaths = [];
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
        $this->removedPaths[] = $relativePath;
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
