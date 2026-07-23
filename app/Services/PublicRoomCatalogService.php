<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PublicRoomCatalogException;
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
        try { return array_map(fn ($room): array => $this->map($room, false), $this->rooms->findAllPublic()); }
        catch (Throwable $failure) { throw PublicRoomCatalogException::failure($failure); }
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) !== 1) {
            throw PublicRoomCatalogException::notFound();
        }
        try {
            $room = $this->rooms->findPublicBySlug($slug);
            if ($room === null) { throw PublicRoomCatalogException::notFound(); }
            return $this->map($room, true);
        } catch (PublicRoomCatalogException $failure) { throw $failure; }
        catch (Throwable $failure) { throw PublicRoomCatalogException::failure($failure); }
    }

    /** @return array<string, mixed> */
    private function map(\App\Models\Room $room, bool $detail): array
    {
        $primary = $this->images->findPrimaryByRoomId($room->id());
        $images = $detail ? $this->images->findAllByRoomId($room->id()) : [];
        return [
            'name' => $room->name(), 'slug' => $room->slug(), 'description' => $room->description() ?? '',
            'roomTypeName' => $room->roomType()->name(), 'pricePerNight' => $room->pricePerNight(),
            'capacityAdults' => $room->capacityAdults(), 'capacityChildren' => $room->capacityChildren(),
            'amenities' => array_map(static fn ($amenity): array => ['name' => $amenity->name()], $this->rooms->findActiveAmenitiesByRoomId($room->id())),
            'primaryImageUrl' => $primary === null ? self::PLACEHOLDER : $this->safeImageUrl($primary->filePath()),
            'images' => array_map(fn ($image): array => ['url' => $this->safeImageUrl($image->filePath()), 'altText' => $image->altText() ?? $room->name()], $images),
        ];
    }

    private function safeImageUrl(string $path): string
    {
        try { return $this->imageUrl->fromStoredPath($path); } catch (Throwable) { return self::PLACEHOLDER; }
    }
}
