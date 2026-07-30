<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GalleryException;
use App\Models\AuditLogEntry;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Requests\GalleryCategoryRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Requests\GalleryItemMetadataRequest;
use App\Requests\GalleryReorderRequest;
use App\Services\AdministratorGalleryService;
use App\Services\GalleryTransaction;
use App\Storage\GalleryImageStorage;
use App\Storage\StoredGalleryImage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class AdministratorGalleryServiceTest extends TestCase
{
    public function testCategoryCreationCommitsWithAuditEvidence(): void
    {
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::once())->method('createCategory')
            ->with('Nature', 'nature', true)
            ->willReturn($this->category());
        $audit = new AdministratorGalleryAuditFake();
        $transaction = new AdministratorGalleryTransactionFake();

        $category = $this->service($repository, new AdministratorGalleryStorageFake(), $audit, $transaction)
            ->createCategory(
                GalleryCategoryRequest::fromInput('Nature', 'nature', true),
                91,
                '127.0.0.1',
                'PHPUnit',
            );

        self::assertSame(7, $category->id());
        self::assertSame(1, $transaction->begins);
        self::assertSame(1, $transaction->commits);
        self::assertSame(0, $transaction->rollbacks);
        self::assertCount(1, $audit->entries);
        self::assertSame('gallery_category_created', $audit->entries[0]->eventType());
        self::assertSame('gallery_category', $audit->entries[0]->entityType());
        self::assertSame(7, $audit->entries[0]->entityId());
    }

    public function testItemUpdatePreservesStoredPathAndRequiresExistingCategory(): void
    {
        $current = $this->item();
        $category = $this->category(id: 9, name: 'Rooms', slug: 'rooms');
        $updated = $this->item(
            categoryId: 9,
            title: 'Updated image',
            caption: null,
            altText: 'Updated accessible description',
            sortOrder: 20,
        );
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::once())->method('lockItemById')->with(11)->willReturn($current);
        $repository->expects(self::once())->method('lockCategoryById')->with(9)->willReturn($category);
        $repository->expects(self::once())->method('updateItem')->with(
            11,
            9,
            'Updated image',
            null,
            $current->filePath(),
            'Updated accessible description',
            20,
        )->willReturn($updated);
        $audit = new AdministratorGalleryAuditFake();

        $result = $this->service(
            $repository,
            new AdministratorGalleryStorageFake(),
            $audit,
            new AdministratorGalleryTransactionFake(),
        )->updateItem(
            11,
            GalleryItemMetadataRequest::fromInput(
                9,
                'Updated image',
                '',
                'Updated accessible description',
                20,
            ),
            91,
            null,
            null,
        );

        self::assertSame($current->filePath(), $result->filePath());
        self::assertSame(9, $result->categoryId());
        self::assertSame('gallery_item_updated', $audit->entries[0]->eventType());
    }

    public function testActivePublicationAndRemovalRemainIndependentAndRetainRecord(): void
    {
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $active = $this->item(active: true, published: false);
        $inactive = $this->item(active: false, published: false);
        $published = $this->item(active: false, published: true);
        $removed = $this->item(active: false, published: false);
        $repository->expects(self::exactly(3))->method('lockItemById')
            ->willReturnOnConsecutiveCalls($active, $inactive, $published);
        $repository->expects(self::once())->method('setItemActive')->with(11, false)->willReturn($inactive);
        $repository->expects(self::once())->method('setItemPublished')->with(11, true)->willReturn($published);
        $repository->expects(self::once())->method('deactivateAndUnpublishItem')->with(11)->willReturn($removed);
        $audit = new AdministratorGalleryAuditFake();
        $service = $this->service(
            $repository,
            new AdministratorGalleryStorageFake(),
            $audit,
            new AdministratorGalleryTransactionFake(),
        );

        self::assertFalse($service->setItemActive(11, false, 91, null, null)->isActive());
        $publishedResult = $service->setItemPublished(11, true, 91, null, null);
        self::assertFalse($publishedResult->isActive());
        self::assertTrue($publishedResult->isPublished());
        $removedResult = $service->removeItem(11, 91, null, null);
        self::assertFalse($removedResult->isActive());
        self::assertFalse($removedResult->isPublished());
        self::assertSame([
            'gallery_item_active_changed',
            'gallery_item_publication_changed',
            'gallery_item_removed',
        ], array_map(
            static fn (AuditLogEntry $entry): string => $entry->eventType(),
            $audit->entries,
        ));
    }

    public function testReorderLocksEveryItemAndWritesOneAggregateAuditRecord(): void
    {
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $first = $this->item(id: 11, sortOrder: 10);
        $second = $this->item(id: 12, sortOrder: 20);
        $repository->expects(self::exactly(2))->method('lockItemById')
            ->willReturnMap([[11, $first], [12, $second]]);
        $repository->expects(self::exactly(2))->method('setItemSortOrder')
            ->willReturnMap([
                [11, 30, $this->item(id: 11, sortOrder: 30)],
                [12, 10, $this->item(id: 12, sortOrder: 10)],
            ]);
        $audit = new AdministratorGalleryAuditFake();

        $items = $this->service(
            $repository,
            new AdministratorGalleryStorageFake(),
            $audit,
            new AdministratorGalleryTransactionFake(),
        )->reorder(
            GalleryReorderRequest::fromInput([
                ['id' => 11, 'sort_order' => 30],
                ['id' => 12, 'sort_order' => 10],
            ]),
            91,
            null,
            null,
        );

        self::assertSame([30, 10], array_map(
            static fn (GalleryItem $item): int => $item->sortOrder(),
            $items,
        ));
        self::assertCount(1, $audit->entries);
        self::assertSame('gallery_items_reordered', $audit->entries[0]->eventType());
        self::assertNull($audit->entries[0]->entityId());
        self::assertStringContainsString('"items"', $audit->entries[0]->metadataJson() ?? '');
    }

    public function testCreateStoresBytesAndCompensatesWhenAuditFails(): void
    {
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::once())->method('createItem')->willReturn($this->item());
        $storage = new AdministratorGalleryStorageFake();
        $audit = new AdministratorGalleryAuditFake();
        $audit->failure = new RuntimeException('sensitive audit failure');
        $transaction = new AdministratorGalleryTransactionFake();

        try {
            $this->service($repository, $storage, $audit, $transaction)->createItem(
                GalleryItemMetadataRequest::fromInput(
                    null,
                    'Riverside Sunrise',
                    'Morning light.',
                    'Sunrise over the river',
                    10,
                ),
                $this->uploadRequest(),
                true,
                false,
                91,
                null,
                null,
            );
            self::fail('Expected a safe gallery failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive audit failure', $exception->getMessage());
        }

        self::assertSame(1, $storage->stores);
        self::assertSame([$storage->stored->relativePath()], $storage->removed);
        self::assertSame(1, $transaction->rollbacks);
        self::assertSame(0, $transaction->commits);
    }

    public function testCommitUncertaintyDoesNotDeletePotentiallyCommittedImage(): void
    {
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::once())->method('createItem')->willReturn($this->item());
        $storage = new AdministratorGalleryStorageFake();
        $transaction = new AdministratorGalleryTransactionFake();
        $transaction->commitFailureAfterClosing = new RuntimeException('commit connection dropped');

        try {
            $this->service(
                $repository,
                $storage,
                new AdministratorGalleryAuditFake(),
                $transaction,
            )->createItem(
                GalleryItemMetadataRequest::fromInput(
                    null,
                    'Riverside Sunrise',
                    null,
                    'Sunrise over the river',
                    10,
                ),
                $this->uploadRequest(),
                true,
                true,
                91,
                null,
                null,
            );
            self::fail('Expected a safe commit failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertSame([], $storage->removed);
        self::assertSame(0, $transaction->rollbacks);
    }

    public function testUnexpectedReadsAndExistingTransactionsFailClosed(): void
    {
        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findAllItems')->willThrowException(new RuntimeException('sensitive SQL'));
        $service = $this->service(
            $repository,
            new AdministratorGalleryStorageFake(),
            new AdministratorGalleryAuditFake(),
            new AdministratorGalleryTransactionFake(),
        );

        try {
            $service->listItems();
            self::fail('Expected a safe read failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('sensitive SQL', $exception->getMessage());
        }

        $activeTransaction = new AdministratorGalleryTransactionFake();
        $activeTransaction->active = true;
        $this->expectException(GalleryException::class);
        $this->service(
            $this->createStub(GalleryCatalogRepository::class),
            new AdministratorGalleryStorageFake(),
            new AdministratorGalleryAuditFake(),
            $activeTransaction,
        )->createCategory(
            GalleryCategoryRequest::fromInput('Nature', 'nature', true),
            91,
            null,
            null,
        );
    }

    private function service(
        GalleryCatalogRepository $repository,
        GalleryImageStorage $storage,
        AuditLogWriteRepository $audit,
        GalleryTransaction $transaction,
    ): AdministratorGalleryService {
        return new AdministratorGalleryService(
            $repository,
            $storage,
            $audit,
            $transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-30 10:00:00 UTC'),
        );
    }

    private function category(
        int $id = 7,
        string $name = 'Nature',
        string $slug = 'nature',
        bool $active = true,
    ): GalleryCategory {
        return new GalleryCategory(
            $id,
            $name,
            $slug,
            $active,
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 09:00:00 UTC'),
        );
    }

    private function item(
        int $id = 11,
        ?int $categoryId = null,
        string $title = 'Riverside Sunrise',
        ?string $caption = 'Morning light.',
        string $altText = 'Sunrise over the river',
        int $sortOrder = 10,
        bool $active = true,
        bool $published = true,
    ): GalleryItem {
        return new GalleryItem(
            $id,
            $categoryId,
            $title,
            $caption,
            'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg',
            $altText,
            $sortOrder,
            $active,
            $published,
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 09:00:00 UTC'),
        );
    }

    private function uploadRequest(): GalleryImageUploadRequest
    {
        return (new ReflectionClass(GalleryImageUploadRequest::class))->newInstanceWithoutConstructor();
    }
}

final class AdministratorGalleryAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public ?Throwable $failure = null;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->entries[] = $entry;
    }
}

final class AdministratorGalleryTransactionFake implements GalleryTransaction
{
    public bool $active = false;
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public ?Throwable $commitFailureAfterClosing = null;

    public function begin(): void
    {
        $this->begins++;
        $this->active = true;
    }

    public function commit(): void
    {
        $this->active = false;

        if ($this->commitFailureAfterClosing !== null) {
            throw $this->commitFailureAfterClosing;
        }

        $this->commits++;
    }

    public function rollBack(): void
    {
        $this->rollbacks++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}

final class AdministratorGalleryStorageFake implements GalleryImageStorage
{
    public int $stores = 0;

    /** @var list<string> */
    public array $removed = [];

    public StoredGalleryImage $stored;

    public function __construct()
    {
        $this->stored = new StoredGalleryImage(
            'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg',
            '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg',
            'jpg',
            'image/jpeg',
            1024,
            800,
            600,
        );
    }

    public function store(GalleryImageUploadRequest $request): StoredGalleryImage
    {
        $this->stores++;

        return $this->stored;
    }

    public function remove(string $relativePath): void
    {
        $this->removed[] = $relativePath;
    }
}
