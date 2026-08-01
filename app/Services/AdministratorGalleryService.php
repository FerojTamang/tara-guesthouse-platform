<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalleryException;
use App\Exceptions\GalleryImageException;
use App\Models\AuditLogEntry;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Requests\GalleryCategoryRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Requests\GalleryItemMetadataRequest;
use App\Requests\GalleryReorderRequest;
use App\Storage\GalleryImageStorage;
use App\Storage\StoredGalleryImage;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Owns authenticated Administrator gallery workflows, compensation, and audit evidence.
 */
final readonly class AdministratorGalleryService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private GalleryCatalogRepository $gallery,
        private GalleryImageStorage $storage,
        private AuditLogWriteRepository $auditLogs,
        private GalleryTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ),
        );
    }

    /** @return list<GalleryCategory> */
    public function listCategories(): array
    {
        return $this->read(fn (): array => $this->gallery->findAllCategories());
    }

    /** @return list<GalleryItem> */
    public function listItems(): array
    {
        return $this->read(fn (): array => $this->gallery->findAllItems());
    }

    public function getCategory(int $id): GalleryCategory
    {
        return $this->read(function () use ($id): GalleryCategory {
            $category = $this->gallery->findCategoryById($id);

            if (!$category instanceof GalleryCategory) {
                throw GalleryException::categoryNotFound();
            }

            return $category;
        });
    }

    public function getItem(int $id): GalleryItem
    {
        return $this->read(function () use ($id): GalleryItem {
            $item = $this->gallery->findItemById($id);

            if (!$item instanceof GalleryItem) {
                throw GalleryException::itemNotFound();
            }

            return $item;
        });
    }

    public function createCategory(
        GalleryCategoryRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryCategory {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $request,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): GalleryCategory {
            $category = $this->gallery->createCategory(
                $request->name(),
                $request->slug(),
                $request->isActive(),
            );
            $this->appendAudit(
                $administratorId,
                'gallery_category_created',
                'gallery_category',
                $category->id(),
                'Administrator created a gallery category.',
                [
                    'name' => $category->name(),
                    'slug' => $category->slug(),
                    'is_active' => $category->isActive(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $category;
        });
    }

    public function updateCategory(
        int $id,
        GalleryCategoryRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryCategory {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $id,
            $request,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): GalleryCategory {
            $current = $this->lockedCategory($id);
            $updated = $this->gallery->updateCategory(
                $current->id(),
                $request->name(),
                $request->slug(),
            );

            if (!$updated instanceof GalleryCategory) {
                throw GalleryException::categoryNotFound();
            }

            if ($updated->isActive() !== $request->isActive()) {
                $updated = $this->gallery->setCategoryActive(
                    $updated->id(),
                    $request->isActive(),
                );
            }

            if (!$updated instanceof GalleryCategory) {
                throw GalleryException::categoryNotFound();
            }

            $this->appendAudit(
                $administratorId,
                'gallery_category_updated',
                'gallery_category',
                $updated->id(),
                'Administrator updated a gallery category.',
                [
                    'previous_name' => $current->name(),
                    'name' => $updated->name(),
                    'previous_slug' => $current->slug(),
                    'slug' => $updated->slug(),
                    'previous_is_active' => $current->isActive(),
                    'is_active' => $updated->isActive(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    public function createItem(
        GalleryItemMetadataRequest $metadata,
        GalleryImageUploadRequest $upload,
        bool $active,
        bool $published,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryItem {
        $this->assertAdministrator($administratorId);
        $this->rejectExistingTransaction();
        $stored = null;
        $commitAttempted = false;

        try {
            if ($metadata->categoryId() !== null) {
                $this->getCategory($metadata->categoryId());
            }

            $stored = $this->storage->store($upload);
            $this->transaction->begin();
            $this->requireCategory($metadata->categoryId());
            $item = $this->gallery->createItem(
                $metadata->categoryId(),
                $metadata->title(),
                $metadata->caption(),
                $stored->relativePath(),
                $metadata->alternativeText(),
                $metadata->sortOrder(),
                $active,
                $published,
                $metadata->isHomepageFeatured(),
                $metadata->homepageSortOrder(),
            );
            $this->appendAudit(
                $administratorId,
                'gallery_item_created',
                'gallery_item',
                $item->id(),
                'Administrator uploaded and created a gallery item.',
                [
                    'gallery_category_id' => $item->categoryId(),
                    'file_path' => $item->filePath(),
                    'media_type' => $stored->mediaType(),
                    'byte_size' => $stored->byteSize(),
                    'width' => $stored->width(),
                    'height' => $stored->height(),
                    'is_active' => $item->isActive(),
                    'is_published' => $item->isPublished(),
                    'sort_order' => $item->sortOrder(),
                ],
                $ipAddress,
                $userAgent,
            );
            $commitAttempted = true;
            $this->transaction->commit();

            return $item;
        } catch (Throwable $failure) {
            $safeFailure = $this->safeFailure($failure);

            try {
                $activeTransaction = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw GalleryException::failure($stateFailure);
            }

            if ($activeTransaction) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw GalleryException::failure($rollbackFailure);
                }
            }

            if (!$activeTransaction && $commitAttempted) {
                throw GalleryException::failure($safeFailure);
            }

            if ($stored instanceof StoredGalleryImage) {
                try {
                    $this->storage->remove($stored->relativePath());
                } catch (Throwable $cleanupFailure) {
                    throw GalleryException::failure($cleanupFailure);
                }
            }

            throw $safeFailure;
        }
    }

    public function updateItem(
        int $id,
        GalleryItemMetadataRequest $metadata,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryItem {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $id,
            $metadata,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): GalleryItem {
            $current = $this->lockedItem($id);
            $this->requireCategory($metadata->categoryId());
            $updated = $this->gallery->updateItem(
                $current->id(),
                $metadata->categoryId(),
                $metadata->title(),
                $metadata->caption(),
                $current->filePath(),
                $metadata->alternativeText(),
                $metadata->sortOrder(),
                $metadata->isHomepageFeatured(),
                $metadata->homepageSortOrder(),
            );

            if (!$updated instanceof GalleryItem) {
                throw GalleryException::itemNotFound();
            }

            $this->appendAudit(
                $administratorId,
                'gallery_item_updated',
                'gallery_item',
                $updated->id(),
                'Administrator updated gallery item metadata.',
                [
                    'previous_gallery_category_id' => $current->categoryId(),
                    'gallery_category_id' => $updated->categoryId(),
                    'previous_title' => $current->title(),
                    'title' => $updated->title(),
                    'previous_sort_order' => $current->sortOrder(),
                    'sort_order' => $updated->sortOrder(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    public function setItemActive(
        int $id,
        bool $active,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryItem {
        return $this->setItemBooleanState(
            $id,
            'active',
            $active,
            $administratorId,
            $ipAddress,
            $userAgent,
        );
    }

    public function setItemPublished(
        int $id,
        bool $published,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryItem {
        return $this->setItemBooleanState(
            $id,
            'publication',
            $published,
            $administratorId,
            $ipAddress,
            $userAgent,
        );
    }

    public function reorder(
        GalleryReorderRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $request,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): array {
            $updated = [];
            $changes = [];

            foreach ($request->items() as $requested) {
                $current = $this->lockedItem($requested['id']);
                $item = $current;

                if ($current->sortOrder() !== $requested['sort_order']) {
                    $item = $this->gallery->setItemSortOrder(
                        $current->id(),
                        $requested['sort_order'],
                    );

                    if (!$item instanceof GalleryItem) {
                        throw GalleryException::itemNotFound();
                    }

                    $changes[] = [
                        'id' => $item->id(),
                        'from' => $current->sortOrder(),
                        'to' => $item->sortOrder(),
                    ];
                }

                $updated[] = $item;
            }

            if ($changes !== []) {
                $this->appendAudit(
                    $administratorId,
                    'gallery_items_reordered',
                    'gallery_item',
                    null,
                    'Administrator updated gallery item ordering.',
                    ['items' => $changes],
                    $ipAddress,
                    $userAgent,
                );
            }

            return $updated;
        });
    }

    public function removeItem(
        int $id,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryItem {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $id,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): GalleryItem {
            $current = $this->lockedItem($id);

            if (!$current->isActive() && !$current->isPublished()) {
                return $current;
            }

            $updated = $this->gallery->deactivateAndUnpublishItem($current->id());

            if (!$updated instanceof GalleryItem) {
                throw GalleryException::itemNotFound();
            }

            $this->appendAudit(
                $administratorId,
                'gallery_item_removed',
                'gallery_item',
                $updated->id(),
                'Administrator deactivated and unpublished a gallery item.',
                [
                    'file_path' => $current->filePath(),
                    'previous_is_active' => $current->isActive(),
                    'previous_is_published' => $current->isPublished(),
                    'is_active' => $updated->isActive(),
                    'is_published' => $updated->isPublished(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    private function setItemBooleanState(
        int $id,
        string $state,
        bool $enabled,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): GalleryItem {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $id,
            $state,
            $enabled,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): GalleryItem {
            $current = $this->lockedItem($id);
            $currentValue = match ($state) {
                'active' => $current->isActive(),
                'publication' => $current->isPublished(),
                default => throw new RuntimeException('Gallery state is invalid.'),
            };

            if ($currentValue === $enabled) {
                return $current;
            }

            $updated = match ($state) {
                'active' => $this->gallery->setItemActive($current->id(), $enabled),
                'publication' => $this->gallery->setItemPublished($current->id(), $enabled),
                default => throw new RuntimeException('Gallery state is invalid.'),
            };

            if (!$updated instanceof GalleryItem) {
                throw GalleryException::itemNotFound();
            }

            $this->appendAudit(
                $administratorId,
                $state === 'active'
                    ? 'gallery_item_active_changed'
                    : 'gallery_item_publication_changed',
                'gallery_item',
                $updated->id(),
                $state === 'active'
                    ? 'Administrator changed gallery item active state.'
                    : 'Administrator changed gallery item publication state.',
                [
                    'file_path' => $current->filePath(),
                    'from' => $currentValue,
                    'to' => $enabled,
                ],
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    private function lockedCategory(int $id): GalleryCategory
    {
        if ($id <= 0) {
            throw GalleryException::categoryNotFound();
        }

        $category = $this->gallery->lockCategoryById($id);

        if (!$category instanceof GalleryCategory) {
            throw GalleryException::categoryNotFound();
        }

        return $category;
    }

    private function lockedItem(int $id): GalleryItem
    {
        if ($id <= 0) {
            throw GalleryException::itemNotFound();
        }

        $item = $this->gallery->lockItemById($id);

        if (!$item instanceof GalleryItem) {
            throw GalleryException::itemNotFound();
        }

        return $item;
    }

    private function requireCategory(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $this->lockedCategory($categoryId);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function appendAudit(
        int $administratorId,
        string $eventType,
        string $entityType,
        ?int $entityId,
        string $description,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        try {
            $metadataJson = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException('Gallery audit metadata could not be encoded.', previous: $failure);
        }

        $this->auditLogs->append(new AuditLogEntry(
            $administratorId,
            $eventType,
            $entityType,
            $entityId,
            $description,
            $metadataJson,
            $ipAddress,
            $userAgent,
            $this->now(),
        ));
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw GalleryException::failure();
        }

        return $value;
    }

    private function assertAdministrator(int $administratorId): void
    {
        if ($administratorId <= 0) {
            throw GalleryException::invalidInput('Administrator gallery request is invalid.');
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function read(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryException::failure($failure);
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function transact(callable $operation): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $result = $operation();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
        } catch (Throwable $failure) {
            $safeFailure = $this->safeFailure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw GalleryException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw GalleryException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw GalleryException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw GalleryException::failure();
            }
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GalleryException::failure($failure);
        }
    }

    private function safeFailure(Throwable $failure): GalleryException
    {
        if ($failure instanceof GalleryException) {
            return $failure;
        }

        if ($failure instanceof GalleryImageException && $failure->isValidationFailure()) {
            return GalleryException::invalidInput($failure->getMessage());
        }

        return GalleryException::failure($failure);
    }
}
