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

    private ?RoomTypeCatalogService $roomTypes = null;

    private ?AmenityCatalogService $amenities = null;

    /**
     * @param callable(): mixed $roomTypeFactory
     * @param callable(): mixed $amenityFactory
     */
    public function __construct(callable $roomTypeFactory, callable $amenityFactory)
    {
        $this->roomTypeFactory = Closure::fromCallable($roomTypeFactory);
        $this->amenityFactory = Closure::fromCallable($amenityFactory);
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
