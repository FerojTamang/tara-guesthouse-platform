<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomTypeCatalogRepository;
use App\Requests\RoomUpsertRequest;
use Throwable;

/**
 * Coordinates physical-room administration rules and transactions.
 */
final readonly class RoomCatalogService
{
    public function __construct(
        private RoomCatalogRepository $rooms,
        private RoomTypeCatalogRepository $roomTypes,
        private AmenityCatalogRepository $amenities,
        private RoomCatalogTransaction $transaction,
    ) {
    }

    /** @return list<Room> */
    public function listAll(): array
    {
        return $this->execute(fn (): array => $this->rooms->findAll());
    }

    public function get(int $id): Room
    {
        return $this->execute(fn (): Room => $this->requireRoom($id));
    }

    /** @return list<int> */
    public function amenityIds(int $roomId): array
    {
        return $this->execute(function () use ($roomId): array {
            $this->requireRoom($roomId);

            return $this->rooms->findAmenityIds($roomId);
        });
    }

    public function create(RoomUpsertRequest $request): Room
    {
        return $this->execute(fn (): Room => $this->transactional(function () use ($request): Room {
            $this->requireReferences($request);
            $room = $this->rooms->create(
                $request->roomTypeId(),
                $request->roomNumber(),
                $request->name(),
                $request->slug(),
                $request->description(),
                $request->pricePerNight(),
                $request->capacityAdults(),
                $request->capacityChildren(),
                $request->operationalStatus(),
                $request->isPublished(),
            );
            $this->rooms->replaceAmenities($room->id(), $request->amenityIds());

            return $room;
        }));
    }

    public function update(int $id, RoomUpsertRequest $request): Room
    {
        return $this->execute(fn (): Room => $this->transactional(function () use ($id, $request): Room {
            $this->requireRoom($id);
            $this->requireReferences($request);
            $room = $this->rooms->update(
                $id,
                $request->roomTypeId(),
                $request->roomNumber(),
                $request->name(),
                $request->slug(),
                $request->description(),
                $request->pricePerNight(),
                $request->capacityAdults(),
                $request->capacityChildren(),
                $request->operationalStatus(),
                $request->isPublished(),
            );

            if (!$room instanceof Room) {
                throw RoomCatalogException::roomNotFound();
            }

            $this->rooms->replaceAmenities($room->id(), $request->amenityIds());

            return $room;
        }));
    }

    public function softDelete(int $id): Room
    {
        return $this->execute(fn (): Room => $this->transactional(function () use ($id): Room {
            $room = $this->rooms->softDelete($id);

            if (!$room instanceof Room) {
                throw RoomCatalogException::roomNotFound();
            }

            return $room;
        }));
    }

    private function requireRoom(int $id): Room
    {
        $room = $this->rooms->findById($id);

        if (!$room instanceof Room) {
            throw RoomCatalogException::roomNotFound();
        }

        return $room;
    }

    private function requireReferences(RoomUpsertRequest $request): void
    {
        if (!$this->roomTypes->findById($request->roomTypeId()) instanceof RoomType) {
            throw RoomCatalogException::roomTypeNotFound();
        }

        foreach ($request->amenityIds() as $amenityId) {
            if (!$this->amenities->findById($amenityId) instanceof Amenity) {
                throw RoomCatalogException::amenityNotFound();
            }
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function transactional(callable $operation): mixed
    {
        $this->transaction->begin();

        try {
            $result = $operation();
            $this->transaction->commit();

            return $result;
        } catch (Throwable $throwable) {
            try {
                if ($this->transaction->inProgress()) {
                    $this->transaction->rollBack();
                }
            } catch (Throwable $rollbackFailure) {
                throw RoomCatalogException::failure($rollbackFailure);
            }

            throw $throwable;
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw RoomCatalogException::failure($throwable);
        }
    }
}
