<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PublicRoomCatalogException;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomImage;
use App\Presentation\RoomImageUrl;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use Throwable;

final readonly class PublicRoomCatalogService
{
    private const PLACEHOLDER = '/assets/images/room-placeholder.svg';

    public function __construct(
        private RoomCatalogRepository $rooms,
        private RoomImageCatalogRepository $images,
        private RoomImageUrl $imageUrl,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        try {
            return array_values($this->mapMany($this->rooms->findAllPublic(), false));
        } catch (Throwable $failure) {
            throw PublicRoomCatalogException::failure($failure);
        }
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        if (!PublicRoomSlug::isCanonical($slug)) {
            throw PublicRoomCatalogException::notFound();
        }

        try {
            $room = $this->rooms->findPublicBySlug($slug);
            if (!$room instanceof Room) {
                throw PublicRoomCatalogException::notFound();
            }

            return $this->map($room, true);
        } catch (PublicRoomCatalogException $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw PublicRoomCatalogException::failure($failure);
        }
    }

    /** @return array<string, mixed> */
    public function getById(int $roomId): array
    {
        if ($roomId <= 0) {
            throw PublicRoomCatalogException::notFound();
        }

        try {
            $room = $this->rooms->findPublicById($roomId);
            if (!$room instanceof Room) {
                throw PublicRoomCatalogException::notFound();
            }

            return $this->map($room, true);
        } catch (PublicRoomCatalogException $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw PublicRoomCatalogException::failure($failure);
        }
    }

    /**
     * Projects only requested public rooms with one bulk amenity query and one bulk primary-image query.
     *
     * @param list<int> $roomIds
     * @return array<int, array<string, mixed>>
     */
    public function getManyByIds(array $roomIds): array
    {
        $identifiers = $this->normalizeIdentifiers($roomIds);
        if ($identifiers === []) {
            return [];
        }

        try {
            $mapped = $this->mapMany($this->rooms->findPublicByIds($identifiers), false);
            $ordered = [];
            foreach ($identifiers as $roomId) {
                if (isset($mapped[$roomId])) {
                    $ordered[$roomId] = $mapped[$roomId];
                }
            }

            return $ordered;
        } catch (Throwable $failure) {
            throw PublicRoomCatalogException::failure($failure);
        }
    }

    /**
     * @param list<Room> $rooms
     * @return array<int, array<string, mixed>>
     */
    private function mapMany(array $rooms, bool $detail): array
    {
        $roomIds = [];
        foreach ($rooms as $room) {
            if (!$room instanceof Room) {
                throw PublicRoomCatalogException::failure();
            }
            $roomIds[] = $room->id();
        }

        if ($roomIds === []) {
            return [];
        }

        $primaryImages = $this->images->findPrimaryByRoomIds($roomIds);
        $amenities = $this->rooms->findActiveAmenitiesByRoomIds($roomIds);
        $mapped = [];

        foreach ($rooms as $room) {
            $mapped[$room->id()] = $this->mapLoaded(
                $room,
                $detail,
                $primaryImages[$room->id()] ?? null,
                $amenities[$room->id()] ?? [],
                [],
            );
        }

        return $mapped;
    }

    /** @return array<string, mixed> */
    private function map(Room $room, bool $detail): array
    {
        return $this->mapLoaded(
            $room,
            $detail,
            $this->images->findPrimaryByRoomId($room->id()),
            $this->rooms->findActiveAmenitiesByRoomId($room->id()),
            $detail ? $this->images->findAllByRoomId($room->id()) : [],
        );
    }

    /**
     * @param list<Amenity> $amenities
     * @param list<RoomImage> $images
     * @return array<string, mixed>
     */
    private function mapLoaded(
        Room $room,
        bool $detail,
        ?RoomImage $primary,
        array $amenities,
        array $images,
    ): array {
        $primaryUrl = $primary === null ? self::PLACEHOLDER : $this->safeImageUrl($primary->filePath());
        $publicImages = [];
        $seenUrls = [];

        if ($detail) {
            foreach ($images as $image) {
                if (!$image instanceof RoomImage) {
                    throw PublicRoomCatalogException::failure();
                }

                $url = $this->safeImageUrl($image->filePath());
                if ($url === self::PLACEHOLDER || isset($seenUrls[$url])) {
                    continue;
                }

                $seenUrls[$url] = true;
                $publicImages[] = [
                    'url' => $url,
                    'altText' => $image->altText() ?? $room->name(),
                    'isPrimary' => $url === $primaryUrl,
                ];
            }
        }

        $publicAmenities = [];
        foreach ($amenities as $amenity) {
            if (!$amenity instanceof Amenity) {
                throw PublicRoomCatalogException::failure();
            }
            $publicAmenities[] = ['name' => $amenity->name()];
        }

        return [
            'name' => $room->name(),
            'slug' => $room->slug(),
            'description' => $room->description() ?? '',
            'roomTypeName' => $room->roomType()->name(),
            'pricePerNight' => $room->pricePerNight(),
            'capacityAdults' => $room->capacityAdults(),
            'capacityChildren' => $room->capacityChildren(),
            'amenities' => $publicAmenities,
            'primaryImageUrl' => $primaryUrl,
            'images' => $publicImages,
            'imageCount' => count($publicImages),
        ];
    }

    /**
     * @param list<int> $roomIds
     * @return list<int>
     */
    private function normalizeIdentifiers(array $roomIds): array
    {
        $normalized = [];
        foreach ($roomIds as $roomId) {
            if (is_int($roomId) && $roomId > 0) {
                $normalized[$roomId] = $roomId;
            }
        }

        return array_values($normalized);
    }

    private function safeImageUrl(string $path): string
    {
        try {
            return $this->imageUrl->fromStoredPath($path);
        } catch (Throwable) {
            return self::PLACEHOLDER;
        }
    }
}
