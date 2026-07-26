<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AvailabilityException;
use App\Models\Booking;
use App\Models\Room;
use App\Repositories\RoomCatalogRepository;
use App\Requests\AvailabilityRequest;
use Throwable;

/**
 * Combines calculated availability with the approved public room response fields.
 */
final readonly class PublicAvailabilityService
{
    public function __construct(
        private AvailabilityService $availability,
        private RoomCatalogRepository $rooms,
    ) {
    }

    /**
     * @return list<array{
     *     room_id: int,
     *     name: string,
     *     slug: string,
     *     operational_status: string,
     *     is_published: bool,
     *     is_available: bool,
     *     currency: string,
     *     price_per_night: string
     * }>
     */
    public function search(AvailabilityRequest $request): array
    {
        try {
            $result = $this->availability->search($request);
            $availableIds = $result->roomIds();

            if ($availableIds === []) {
                return [];
            }

            $publicRooms = [];

            foreach ($this->rooms->findAllPublic() as $room) {
                $publicRooms[$room->id()] = $room;
            }

            $response = [];

            foreach ($availableIds as $roomId) {
                $room = $publicRooms[$roomId] ?? null;

                if (!$room instanceof Room) {
                    continue;
                }

                $response[] = [
                    'room_id' => $room->id(),
                    'name' => $room->name(),
                    'slug' => $room->slug(),
                    'operational_status' => Room::STATUS_ACTIVE,
                    'is_published' => true,
                    'is_available' => true,
                    'currency' => Booking::CURRENCY_NPR,
                    'price_per_night' => $room->pricePerNight(),
                ];
            }

            return $response;
        } catch (AvailabilityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AvailabilityException::failure($failure);
        }
    }
}
