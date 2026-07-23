<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RoomImageException;
use App\Models\Room;
use App\Models\RoomImage;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Requests\RoomImageUploadRequest;
use App\Storage\RoomImageStorage;
use App\Storage\StoredRoomImage;
use Throwable;

final readonly class RoomImageService
{
    public function __construct(
        private RoomCatalogRepository $rooms,
        private RoomImageCatalogRepository $images,
        private RoomImageStorage $storage,
        private RoomCatalogTransaction $transaction,
        private int $maximumImages,
    ) {
        if ($maximumImages <= 0) {
            throw RoomImageException::system();
        }
    }

    /** @return list<RoomImage> */
    public function listForRoom(int $roomId): array
    {
        try {
            if ($roomId <= 0 || !$this->rooms->findById($roomId) instanceof Room) {
                throw RoomImageException::roomNotFound();
            }

            return $this->images->findAllByRoomId($roomId);
        } catch (RoomImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw RoomImageException::system($failure);
        }
    }

    public function upload(RoomImageUploadRequest $request): RoomImage
    {
        $this->rejectExistingTransaction();

        $stored = null;
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $roomId = $request->roomId();
            $room = $this->rooms->lockCurrentById($roomId);

            if (!$room instanceof Room) {
                throw RoomImageException::roomNotFound();
            }

            $count = $this->images->countByRoomId($roomId);

            if ($count >= $this->maximumImages) {
                throw RoomImageException::limitReached($this->maximumImages);
            }

            $sortOrder = $request->sortOrder();

            if ($sortOrder === null) {
                $existing = $this->images->findAllByRoomId($roomId);
                $sortOrder = $existing === []
                    ? 0
                    : min(65535, $existing[array_key_last($existing)]->sortOrder() + 1);
            }

            $primary = $count === 0 || $request->isPrimary();
            $currentPrimary = null;

            if ($count > 0 && $request->isPrimary()) {
                $currentPrimary = $this->images->findPrimaryByRoomId($roomId);
            }

            try {
                $stored = $this->storage->store($request->file(), $request->detectedMediaType());
            } catch (Throwable $failure) {
                throw RoomImageException::storage($failure);
            }

            if ($currentPrimary instanceof RoomImage) {
                $this->images->clearPrimaryByRoomId($roomId);
            }

            $created = $this->images->create(
                $roomId,
                $stored->relativePath(),
                $request->alternativeText(),
                $sortOrder,
                $primary,
            );
            $commitAttempted = true;
            $this->transaction->commit();

            return $created;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof RoomImageException
                ? $failure
                : RoomImageException::system($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw RoomImageException::cleanup($safeFailure, $stateFailure);
            }

            if (!$active && $commitAttempted) {
                throw RoomImageException::system($safeFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();

                    if ($this->transaction->inProgress()) {
                        throw RoomImageException::cleanup(
                            $safeFailure,
                            RoomImageException::system(),
                        );
                    }
                } catch (Throwable $rollbackFailure) {
                    if ($rollbackFailure instanceof RoomImageException && $rollbackFailure->isCleanupFailure()) {
                        throw $rollbackFailure;
                    }

                    throw RoomImageException::cleanup($safeFailure, $rollbackFailure);
                }
            }

            if ($stored instanceof StoredRoomImage) {
                try {
                    $this->storage->remove($stored->relativePath());
                } catch (Throwable $cleanupFailure) {
                    throw RoomImageException::cleanup($safeFailure, $cleanupFailure);
                }
            }

            throw $safeFailure;
        }
    }

    public function updateMetadata(
        int $roomId,
        int $imageId,
        ?string $altText,
        int $sortOrder,
    ): RoomImage {
        return $this->runDatabaseWorkflow(function () use ($roomId, $imageId, $altText, $sortOrder): RoomImage {
            $this->requireCurrentRoom($roomId);
            $this->requireOwnedImage($imageId, $roomId);

            if ($sortOrder < 0) {
                throw RoomImageException::system();
            }

            $updated = $this->images->updateMetadata($imageId, $roomId, $altText, $sortOrder);

            if (!$updated instanceof RoomImage) {
                throw RoomImageException::system();
            }

            return $updated;
        });
    }

    public function setPrimary(int $roomId, int $imageId): RoomImage
    {
        return $this->runDatabaseWorkflow(function () use ($roomId, $imageId): RoomImage {
            $this->requireCurrentRoom($roomId);
            $selected = $this->requireOwnedImage($imageId, $roomId);

            if ($selected->isPrimary()) {
                return $selected;
            }

            $this->images->clearPrimaryByRoomId($roomId);
            $updated = $this->images->setPrimary($imageId, $roomId);

            if (!$updated instanceof RoomImage) {
                throw RoomImageException::system();
            }

            return $updated;
        });
    }

    public function delete(int $roomId, int $imageId): RoomImage
    {
        $deleted = $this->runDatabaseWorkflow(function () use ($roomId, $imageId): RoomImage {
            $this->requireCurrentRoom($roomId);
            $owned = $this->requireOwnedImage($imageId, $roomId);
            $deleted = $this->images->delete($imageId, $roomId);

            if (!$deleted instanceof RoomImage) {
                throw RoomImageException::system();
            }

            if ($owned->isPrimary()) {
                $remaining = $this->images->findAllByRoomId($roomId);

                if ($remaining !== []) {
                    $fallback = $remaining[0] ?? null;

                    if (!$fallback instanceof RoomImage || $fallback->roomId() !== $roomId) {
                        throw RoomImageException::system();
                    }

                    $selected = $this->images->setPrimary($fallback->id(), $roomId);

                    if (!$selected instanceof RoomImage) {
                        throw RoomImageException::system();
                    }
                }
            }

            return $owned;
        });

        try {
            $this->storage->remove($deleted->filePath());
        } catch (Throwable $failure) {
            throw RoomImageException::cleanup(RoomImageException::system($failure), $failure);
        }

        return $deleted;
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw RoomImageException::system();
            }
        } catch (RoomImageException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw RoomImageException::system($failure);
        }
    }

    /**
     * @template T
     * @param callable(): T $workflow
     * @return T
     */
    private function runDatabaseWorkflow(callable $workflow): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $result = $workflow();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof RoomImageException
                ? $failure
                : RoomImageException::system($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw RoomImageException::cleanup($safeFailure, $stateFailure);
            }

            if (!$active && $commitAttempted) {
                throw RoomImageException::system($safeFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();

                    if ($this->transaction->inProgress()) {
                        throw RoomImageException::cleanup($safeFailure, RoomImageException::system());
                    }
                } catch (Throwable $rollbackFailure) {
                    if ($rollbackFailure instanceof RoomImageException && $rollbackFailure->isCleanupFailure()) {
                        throw $rollbackFailure;
                    }

                    throw RoomImageException::cleanup($safeFailure, $rollbackFailure);
                }
            }

            throw $safeFailure;
        }
    }

    private function requireCurrentRoom(int $roomId): Room
    {
        $room = $this->rooms->lockCurrentById($roomId);

        if (!$room instanceof Room) {
            throw RoomImageException::roomNotFound();
        }

        return $room;
    }

    private function requireOwnedImage(int $imageId, int $roomId): RoomImage
    {
        $image = $this->images->findByIdAndRoomId($imageId, $roomId);

        if (!$image instanceof RoomImage) {
            throw RoomImageException::imageNotFound();
        }

        return $image;
    }
}
