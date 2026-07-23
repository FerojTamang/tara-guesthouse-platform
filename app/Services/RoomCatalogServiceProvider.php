<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RoomCatalogException;
use Closure;
use Throwable;

/**
 * Lazily builds database-backed room catalogue services only when a catalogue route is used.
 */
final class RoomCatalogServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $roomTypeFactory;

    /** @var Closure(): mixed */
    private readonly Closure $amenityFactory;

    /** @var null|Closure(): mixed */
    private readonly ?Closure $roomFactory;

    private ?RoomTypeCatalogService $roomTypes = null;

    private ?AmenityCatalogService $amenities = null;

    private ?RoomCatalogService $rooms = null;

    /**
     * @param callable(): mixed $roomTypeFactory
     * @param callable(): mixed $amenityFactory
     * @param null|callable(): mixed $roomFactory
     */
    public function __construct(
        callable $roomTypeFactory,
        callable $amenityFactory,
        ?callable $roomFactory = null,
    ) {
        $this->roomTypeFactory = Closure::fromCallable($roomTypeFactory);
        $this->amenityFactory = Closure::fromCallable($amenityFactory);
        $this->roomFactory = $roomFactory === null ? null : Closure::fromCallable($roomFactory);
    }

    public function roomTypes(): RoomTypeCatalogService
    {
        if ($this->roomTypes !== null) {
            return $this->roomTypes;
        }

        $service = $this->build($this->roomTypeFactory);

        if (!$service instanceof RoomTypeCatalogService) {
            throw RoomCatalogException::failure();
        }

        $this->roomTypes = $service;

        return $this->roomTypes;
    }

    public function amenities(): AmenityCatalogService
    {
        if ($this->amenities !== null) {
            return $this->amenities;
        }

        $service = $this->build($this->amenityFactory);

        if (!$service instanceof AmenityCatalogService) {
            throw RoomCatalogException::failure();
        }

        $this->amenities = $service;

        return $this->amenities;
    }

    public function rooms(): RoomCatalogService
    {
        if ($this->rooms !== null) {
            return $this->rooms;
        }

        if ($this->roomFactory === null) {
            throw RoomCatalogException::failure();
        }

        $service = $this->build($this->roomFactory);

        if (!$service instanceof RoomCatalogService) {
            throw RoomCatalogException::failure();
        }

        $this->rooms = $service;

        return $this->rooms;
    }

    /** @param Closure(): mixed $factory */
    private function build(Closure $factory): mixed
    {
        try {
            return $factory();
        } catch (RoomCatalogException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw RoomCatalogException::failure($throwable);
        }
    }
}
