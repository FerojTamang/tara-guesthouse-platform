<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RoomCatalogException;
use App\Models\RoomType;
use App\Repositories\RoomTypeCatalogRepository;
use App\Requests\RoomTypeUpsertRequest;
use Throwable;

/**
 * Coordinates room-type catalogue administration rules.
 */
final readonly class RoomTypeCatalogService
{
    public function __construct(private RoomTypeCatalogRepository $repository)
    {
    }

    /** @return list<RoomType> */
    public function listAll(): array
    {
        return $this->execute(fn (): array => $this->repository->findAll());
    }

    /** @return list<RoomType> */
    public function listActive(): array
    {
        return $this->execute(fn (): array => $this->repository->findAllActive());
    }

    public function get(int $id): RoomType
    {
        return $this->execute(function () use ($id): RoomType {
            $roomType = $this->repository->findById($id);

            if (!$roomType instanceof RoomType) {
                throw RoomCatalogException::roomTypeNotFound();
            }

            return $roomType;
        });
    }

    public function create(RoomTypeUpsertRequest $request): RoomType
    {
        return $this->execute(fn (): RoomType => $this->repository->create(
            $request->name(),
            $request->slug(),
            $request->description(),
            $request->basePrice(),
            $request->capacityAdults(),
            $request->capacityChildren(),
            $request->isActive(),
        ));
    }

    public function update(int $id, RoomTypeUpsertRequest $request): RoomType
    {
        return $this->execute(function () use ($id, $request): RoomType {
            $roomType = $this->repository->update(
                $id,
                $request->name(),
                $request->slug(),
                $request->description(),
                $request->basePrice(),
                $request->capacityAdults(),
                $request->capacityChildren(),
                $request->isActive(),
            );

            if (!$roomType instanceof RoomType) {
                throw RoomCatalogException::roomTypeNotFound();
            }

            return $roomType;
        });
    }

    public function activate(int $id): RoomType
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): RoomType
    {
        return $this->setActive($id, false);
    }

    private function setActive(int $id, bool $active): RoomType
    {
        return $this->execute(function () use ($id, $active): RoomType {
            $roomType = $this->repository->setActive($id, $active);

            if (!$roomType instanceof RoomType) {
                throw RoomCatalogException::roomTypeNotFound();
            }

            return $roomType;
        });
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
