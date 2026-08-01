<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\AuditLogEntry;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Requests\ActivityContentRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Storage\GalleryImageStorage;
use App\Storage\StoredGalleryImage;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Owns authenticated Administrator activity catalogue mutations and audit evidence.
 */
final readonly class AdministratorActivityService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private ActivityCatalogRepository $activities,
        private AuditLogWriteRepository $auditLogs,
        private ActivityTransaction $transaction,
        ?callable $clock = null,
        private ?GalleryImageStorage $imageStorage = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ),
        );
    }

    /** @return list<Activity> */
    public function listAll(): array
    {
        return $this->executeRead(fn (): array => $this->activities->findAll());
    }

    public function get(int $id): Activity
    {
        return $this->executeRead(function () use ($id): Activity {
            $activity = $this->activities->findById($id);

            if (!$activity instanceof Activity) {
                throw ActivityException::notFound();
            }

            return $activity;
        });
    }

    public function create(
        ActivityContentRequest $request,
        bool $active,
        bool $published,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        ?GalleryImageUploadRequest $upload = null,
    ): Activity {
        $this->assertAdministrator($administratorId);

        $stored = $this->storeImage($upload);
        $cleanupAllowed = true;

        try {
            return $this->transact(function () use (
            $request,
            $active,
            $published,
            $administratorId,
            $ipAddress,
            $userAgent,
            $stored,
        ): Activity {
            $activity = $this->activities->create(
                $request->title(),
                $request->slug(),
                $request->description(),
                $request->price(),
                $active,
                $published,
                $request->sortOrder(),
                $request->summary(),
                $stored?->relativePath(),
                $request->priceUnit(),
                $request->durationLabel(),
                $request->location(),
                $request->isHomepageFeatured(),
                $request->homepageSortOrder(),
            );
            $changedAt = $this->now();
            $this->appendAudit(
                $administratorId,
                'activity_created',
                $activity,
                'Administrator created an activity.',
                [
                    'slug' => $activity->slug(),
                    'price' => $activity->price(),
                    'is_active' => $activity->isActive(),
                    'is_published' => $activity->isPublished(),
                    'sort_order' => $activity->sortOrder(),
                ],
                $ipAddress,
                $userAgent,
                $changedAt,
            );

            return $activity;
            }, $cleanupAllowed);
        } catch (Throwable $failure) {
            if ($cleanupAllowed) {
                $this->removeStoredImage($stored);
            }
            throw $failure;
        }
    }

    public function update(
        int $id,
        ActivityContentRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        ?GalleryImageUploadRequest $upload = null,
    ): Activity {
        $this->assertAdministrator($administratorId);

        $stored = $this->storeImage($upload);
        $previousImagePath = null;
        $cleanupAllowed = true;

        try {
            $updated = $this->transact(function () use (
            $id,
            $request,
            $administratorId,
            $ipAddress,
            $userAgent,
            $stored,
            &$previousImagePath,
        ): Activity {
            $current = $this->locked($id);
            $previousImagePath = $current->featuredImagePath();
            $updated = $this->activities->update(
                $current->id(),
                $request->title(),
                $request->slug(),
                $request->description(),
                $request->price(),
                $request->sortOrder(),
                $request->summary(),
                $stored?->relativePath() ?? $current->featuredImagePath(),
                $request->priceUnit(),
                $request->durationLabel(),
                $request->location(),
                $request->isHomepageFeatured(),
                $request->homepageSortOrder(),
            );

            if (!$updated instanceof Activity) {
                throw ActivityException::notFound();
            }

            $this->appendAudit(
                $administratorId,
                'activity_updated',
                $updated,
                'Administrator updated activity content.',
                [
                    'previous_slug' => $current->slug(),
                    'slug' => $updated->slug(),
                    'previous_price' => $current->price(),
                    'price' => $updated->price(),
                    'previous_sort_order' => $current->sortOrder(),
                    'sort_order' => $updated->sortOrder(),
                ],
                $ipAddress,
                $userAgent,
                $this->now(),
            );

            return $updated;
            }, $cleanupAllowed);

            if ($stored instanceof StoredGalleryImage && is_string($previousImagePath)) {
                $this->imageStorage?->remove($previousImagePath);
            }

            return $updated;
        } catch (Throwable $failure) {
            if ($cleanupAllowed) {
                $this->removeStoredImage($stored);
            }
            throw $failure instanceof ActivityException
                ? $failure
                : ActivityException::failure($failure);
        }
    }

    public function setActive(
        int $id,
        bool $active,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): Activity {
        return $this->setBooleanState(
            $id,
            'active',
            $active,
            $administratorId,
            $ipAddress,
            $userAgent,
        );
    }

    public function setPublished(
        int $id,
        bool $published,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): Activity {
        return $this->setBooleanState(
            $id,
            'publication',
            $published,
            $administratorId,
            $ipAddress,
            $userAgent,
        );
    }

    public function remove(
        int $id,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): Activity {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $id,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): Activity {
            $current = $this->locked($id);

            if (!$current->isActive() && !$current->isPublished()) {
                return $current;
            }

            $updated = $this->activities->deactivateAndUnpublish($current->id());

            if (!$updated instanceof Activity) {
                throw ActivityException::notFound();
            }

            $this->appendAudit(
                $administratorId,
                'activity_removed',
                $updated,
                'Administrator deactivated and unpublished an activity.',
                [
                    'slug' => $current->slug(),
                    'previous_is_active' => $current->isActive(),
                    'previous_is_published' => $current->isPublished(),
                    'is_active' => $updated->isActive(),
                    'is_published' => $updated->isPublished(),
                ],
                $ipAddress,
                $userAgent,
                $this->now(),
            );

            return $updated;
        });
    }

    private function setBooleanState(
        int $id,
        string $state,
        bool $enabled,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): Activity {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use (
            $id,
            $state,
            $enabled,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): Activity {
            $current = $this->locked($id);
            $currentValue = match ($state) {
                'active' => $current->isActive(),
                'publication' => $current->isPublished(),
                default => throw new RuntimeException('Activity state is invalid.'),
            };

            if ($currentValue === $enabled) {
                return $current;
            }

            $updated = match ($state) {
                'active' => $this->activities->setActive($current->id(), $enabled),
                'publication' => $this->activities->setPublished($current->id(), $enabled),
                default => throw new RuntimeException('Activity state is invalid.'),
            };

            if (!$updated instanceof Activity) {
                throw ActivityException::notFound();
            }

            $this->appendAudit(
                $administratorId,
                $state === 'active'
                    ? 'activity_active_changed'
                    : 'activity_publication_changed',
                $updated,
                $state === 'active'
                    ? 'Administrator changed activity active state.'
                    : 'Administrator changed activity publication state.',
                [
                    'slug' => $current->slug(),
                    'from' => $currentValue,
                    'to' => $enabled,
                ],
                $ipAddress,
                $userAgent,
                $this->now(),
            );

            return $updated;
        });
    }

    private function locked(int $id): Activity
    {
        if ($id <= 0) {
            throw ActivityException::notFound();
        }

        $activity = $this->activities->lockById($id);

        if (!$activity instanceof Activity) {
            throw ActivityException::notFound();
        }

        return $activity;
    }

    /**
     * @param array<string, bool|int|string> $metadata
     */
    private function appendAudit(
        int $administratorId,
        string $eventType,
        Activity $activity,
        string $description,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $changedAt,
    ): void {
        try {
            $metadataJson = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException('Activity audit metadata could not be encoded.', previous: $failure);
        }

        $this->auditLogs->append(new AuditLogEntry(
            $administratorId,
            $eventType,
            'activity',
            $activity->id(),
            $description,
            $metadataJson,
            $ipAddress,
            $userAgent,
            $changedAt,
        ));
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw ActivityException::failure();
        }

        return $value;
    }

    private function assertAdministrator(int $administratorId): void
    {
        if ($administratorId <= 0) {
            throw ActivityException::invalidInput('Administrator activity request is invalid.');
        }
    }

    private function storeImage(?GalleryImageUploadRequest $upload): ?StoredGalleryImage
    {
        if ($upload === null) {
            return null;
        }

        if (!$this->imageStorage instanceof GalleryImageStorage) {
            throw ActivityException::failure();
        }

        try {
            return $this->imageStorage->store($upload);
        } catch (Throwable $failure) {
            throw ActivityException::failure($failure);
        }
    }

    private function removeStoredImage(?StoredGalleryImage $stored): void
    {
        if (!$stored instanceof StoredGalleryImage || !$this->imageStorage instanceof GalleryImageStorage) {
            return;
        }

        try {
            $this->imageStorage->remove($stored->relativePath());
        } catch (Throwable $failure) {
            throw ActivityException::failure($failure);
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function executeRead(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ActivityException::failure($failure);
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function transact(callable $operation, ?bool &$cleanupAllowed = null): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;
        $cleanupAllowed = true;

        try {
            $this->transaction->begin();
            $result = $operation();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof ActivityException
                ? $failure
                : ActivityException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                $cleanupAllowed = false;
                throw ActivityException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw ActivityException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                $cleanupAllowed = $active;
                throw ActivityException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw ActivityException::failure();
            }
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ActivityException::failure($failure);
        }
    }
}
