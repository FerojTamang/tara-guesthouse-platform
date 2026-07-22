<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogServiceProvider;

/**
 * Formats authenticated Administrator room catalogue pages.
 */
final readonly class AdministratorRoomCatalogController
{
    public function __construct(
        private ViewRenderer $views,
        private RoomCatalogServiceProvider $services,
        private CsrfTokenManager $csrfTokens,
        private RoomCatalogFlashMessages $flashMessages,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function rooms(Request $request, array $parameters): Response
    {
        $rooms = array_map(
            $this->roomData(...),
            $this->services->rooms()->listAll(),
        );

        return $this->page('admin.rooms.index', [
            'title' => 'Rooms | Tara Guest House',
            'pageTitle' => 'Rooms',
            'activeNavigation' => 'rooms',
            'rooms' => $rooms,
        ]);
    }

    /** @param array<string, string> $parameters */
    public function roomTypes(Request $request, array $parameters): Response
    {
        $roomTypes = array_map(
            $this->roomTypeData(...),
            $this->services->roomTypes()->listAll(),
        );

        return $this->page('admin.room-types.index', [
            'title' => 'Room types | Tara Guest House',
            'pageTitle' => 'Room types',
            'activeNavigation' => 'room-types',
            'roomTypes' => $roomTypes,
            'notice' => $this->flashMessages->pullRoomTypeSuccess(),
        ]);
    }

    /** @param array<string, string> $parameters */
    public function amenities(Request $request, array $parameters): Response
    {
        $amenities = array_map(
            $this->amenityData(...),
            $this->services->amenities()->listAll(),
        );

        return $this->page('admin.amenities.index', [
            'title' => 'Amenities | Tara Guest House',
            'pageTitle' => 'Amenities',
            'activeNavigation' => 'amenities',
            'amenities' => $amenities,
            'notice' => $this->flashMessages->pullAmenitySuccess(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $view, array $data): Response
    {
        $html = $this->views->render(
            $view,
            [...$data, 'csrfToken' => $this->csrfTokens->token()],
            'layouts.admin',
        );

        return Response::html($html, 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * @return array{
     *     id: int,
     *     roomNumber: string,
     *     name: string,
     *     slug: string,
     *     roomTypeName: string,
     *     pricePerNight: string,
     *     capacityAdults: int,
     *     capacityChildren: int,
     *     operationalStatusLabel: string,
     *     operationalStatusBadge: string,
     *     published: bool
     * }
     */
    private function roomData(Room $room): array
    {
        [$statusLabel, $statusBadge] = match ($room->operationalStatus()) {
            Room::STATUS_ACTIVE => ['Active', 'text-bg-success'],
            Room::STATUS_MAINTENANCE => ['Maintenance', 'text-bg-warning'],
            Room::STATUS_INACTIVE => ['Inactive', 'text-bg-secondary'],
        };

        return [
            'id' => $room->id(),
            'roomNumber' => $room->roomNumber(),
            'name' => $room->name(),
            'slug' => $room->slug(),
            'roomTypeName' => $room->roomType()->name(),
            'pricePerNight' => $room->pricePerNight(),
            'capacityAdults' => $room->capacityAdults(),
            'capacityChildren' => $room->capacityChildren(),
            'operationalStatusLabel' => $statusLabel,
            'operationalStatusBadge' => $statusBadge,
            'published' => $room->isPublished(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     basePrice: string,
     *     capacityAdults: int,
     *     capacityChildren: int,
     *     active: bool
     * }
     */
    private function roomTypeData(RoomType $roomType): array
    {
        return [
            'id' => $roomType->id(),
            'name' => $roomType->name(),
            'slug' => $roomType->slug(),
            'basePrice' => $roomType->basePrice(),
            'capacityAdults' => $roomType->capacityAdults(),
            'capacityChildren' => $roomType->capacityChildren(),
            'active' => $roomType->isActive(),
        ];
    }

    /**
     * @return array{id: int, name: string, icon: ?string, active: bool}
     */
    private function amenityData(Amenity $amenity): array
    {
        return [
            'id' => $amenity->id(),
            'name' => $amenity->name(),
            'icon' => $amenity->icon(),
            'active' => $amenity->isActive(),
        ];
    }
}
