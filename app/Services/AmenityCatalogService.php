<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Repositories\AmenityCatalogRepository;
use App\Requests\AmenityUpsertRequest;
use Throwable;

/**
 * Coordinates amenity catalogue administration rules.
 */
final readonly class AmenityCatalogService
{
    public function __construct(private AmenityCatalogRepository $repository)
    {
    }

    /** @return list<Amenity> */
    public function listAll(): array
    {
        return $this->execute(fn (): array => $this->repository->findAll());
    }

    /** @return list<Amenity> */
    public function listActive(): array
    {
        return $this->execute(fn (): array => $this->repository->findAllActive());
    }

    public function get(int $id): Amenity
    {
        return $this->execute(function () use ($id): Amenity {
            $amenity = $this->repository->findById($id);

            if (!$amenity instanceof Amenity) {
                throw RoomCatalogException::amenityNotFound();
            }

            return $amenity;
        });
    }

    public function create(AmenityUpsertRequest $request): Amenity
    {
        return $this->execute(fn (): Amenity => $this->repository->create(
            $request->name(),
            $request->icon(),
            $request->isActive(),
        ));
    }

    public function update(int $id, AmenityUpsertRequest $request): Amenity
    {
        return $this->execute(function () use ($id, $request): Amenity {
            $amenity = $this->repository->update(
                $id,
                $request->name(),
                $request->icon(),
                $request->isActive(),
            );

            if (!$amenity instanceof Amenity) {
                throw RoomCatalogException::amenityNotFound();
            }

            return $amenity;
        });
    }

    public function activate(int $id): Amenity
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): Amenity
    {
        return $this->setActive($id, false);
    }

    private function setActive(int $id, bool $active): Amenity
    {
        return $this->execute(function () use ($id, $active): Amenity {
            $amenity = $this->repository->setActive($id, $active);

            if (!$amenity instanceof Amenity) {
                throw RoomCatalogException::amenityNotFound();
            }

            return $amenity;
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
