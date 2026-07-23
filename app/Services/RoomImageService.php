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

    public function upload(RoomImageUploadRequest $request): RoomImage
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
}
