<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AvailabilityException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\StayPeriod;
use App\Repositories\AvailabilityRepository;
use App\Repositories\RoomCatalogRepository;
use App\Requests\AvailabilityRequest;
use App\Requests\RoomAvailabilityCalendarRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Combines calculated availability with the approved public room response fields.
 */
final class PublicAvailabilityService
{
    /** @var Closure(): mixed */
    private readonly Closure $clock;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly RoomCatalogRepository $rooms,
        private readonly ?PublicRoomCatalogServiceProvider $catalog = null,
        private readonly ?AvailabilityRepository $calendarRepository = null,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    /**
     * @return list<array{
     *     room_id: int,
     *     name: string,
     *     slug: string,
     *     is_available: bool,
     *     currency: string,
     *     price_per_night: string
     * }>
     */
    public function search(AvailabilityRequest $request, ?string $roomSlug = null): array
    {
        return array_map(
            static fn (array $room): array => [
                'room_id' => $room['room_id'],
                'name' => $room['name'],
                'slug' => $room['slug'],
                'is_available' => $room['is_available'],
                'currency' => $room['currency'],
                'price_per_night' => $room['price_per_night'],
            ],
            $this->buildContext($request, $roomSlug, false)['rooms'],
        );
    }

    /**
     * @return array{
     *     rooms: list<array<string, mixed>>,
     *     selectedRoom: ?array<string, mixed>
     * }
     */
    public function searchContext(AvailabilityRequest $request, ?string $roomSlug = null): array
    {
        return $this->buildContext($request, $roomSlug, true);
    }

    /** @return array{rooms: list<array<string, mixed>>, selectedRoom: ?array<string, mixed>} */
    private function buildContext(
        AvailabilityRequest $request,
        ?string $roomSlug,
        bool $enrich,
    ): array
    {
        try {
            $selectedRoom = null;
            $selectedCatalogRoom = null;
            $selectedRoomId = null;
            $publicRooms = [];

            if ($roomSlug !== null) {
                if (!PublicRoomSlug::isCanonical($roomSlug)) {
                    throw AvailabilityException::invalidInput(
                        'The selected room is unavailable.',
                    );
                }

                $room = $this->rooms->findPublicBySlug($roomSlug);

                if (!$room instanceof Room) {
                    throw AvailabilityException::invalidInput(
                        'The selected room is unavailable.',
                    );
                }

                $publicRooms[$room->id()] = $room;
                $selectedRoomId = $room->id();
                if ($enrich && $this->catalog !== null) {
                    $selectedCatalogRoom = $this->catalog->service()->getManyByIds([$room->id()])[$room->id()] ?? null;
                    if (!is_array($selectedCatalogRoom)) {
                        throw new RuntimeException('The selected public room projection is unavailable.');
                    }
                    $selectedRoom = $this->mapCatalogRoom(
                        $room->id(),
                        $selectedCatalogRoom,
                        $request->stayPeriod()->nights(),
                        false,
                    );
                } else {
                    $selectedRoom = $this->map($room, false, $request->stayPeriod()->nights());
                }
            }

            $result = $this->availability->search($request);
            $availableIds = $result->roomIds();

            if (is_array($selectedCatalogRoom) && is_int($selectedRoomId)) {
                $response = in_array($selectedRoomId, $availableIds, true)
                    ? [$this->mapCatalogRoom(
                        $selectedRoomId,
                        $selectedCatalogRoom,
                        $request->stayPeriod()->nights(),
                        true,
                    )]
                    : [];
                $selectedRoom['is_available'] = $response !== [];

                return ['rooms' => $response, 'selectedRoom' => $selectedRoom];
            }

            if ($roomSlug === null) {
                if ($availableIds === []) {
                    return ['rooms' => [], 'selectedRoom' => null];
                }

                if ($enrich && $this->catalog !== null) {
                    $catalogRooms = $this->catalog->service()->getManyByIds($availableIds);
                    $response = [];
                    foreach ($availableIds as $roomId) {
                        $public = $catalogRooms[$roomId] ?? null;
                        if (is_array($public)) {
                            $response[] = $this->mapCatalogRoom(
                                $roomId,
                                $public,
                                $request->stayPeriod()->nights(),
                                true,
                            );
                        }
                    }

                    return ['rooms' => $response, 'selectedRoom' => null];
                }

                foreach ($this->rooms->findAllPublic() as $room) {
                    $publicRooms[$room->id()] = $room;
                }
            }

            $response = [];

            foreach ($availableIds as $roomId) {
                $room = $publicRooms[$roomId] ?? null;

                if (!$room instanceof Room) {
                    continue;
                }

                $response[] = $this->map($room, true, $request->stayPeriod()->nights());
            }

            if ($selectedRoom !== null) {
                $selectedRoom['is_available'] = $response !== [];
            }

            return ['rooms' => $response, 'selectedRoom' => $selectedRoom];
        } catch (AvailabilityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AvailabilityException::failure($failure);
        }
    }

    /** @return array<string, mixed> */
    public function calendar(string $slug, mixed $from, mixed $to): array
    {
        if (!PublicRoomSlug::isCanonical($slug)) {
            throw AvailabilityException::notFound();
        }

        try {
            $now = ($this->clock)();

            if (!$now instanceof DateTimeImmutable || $now->getOffset() !== 0) {
                throw new RuntimeException('The availability calendar clock is invalid.');
            }

            $today = $now->setTimezone(new DateTimeZone('Asia/Kathmandu'));
            $request = RoomAvailabilityCalendarRequest::fromInput($from, $to, $today);
            $room = $this->rooms->findPublicBySlug($slug);

            if (!$room instanceof Room) {
                throw AvailabilityException::notFound();
            }

            if (!$this->calendarRepository instanceof AvailabilityRepository) {
                throw new RuntimeException('The availability calendar repository is unavailable.');
            }

            $window = $request->window();
            $ranges = $this->mergeAndClip(
                $this->calendarRepository->findBlockingStayPeriods($room->id(), $window, $now),
                $window,
            );

            return [
                'room' => ['slug' => $room->slug(), 'name' => $room->name()],
                'from' => $window->checkIn()->format('Y-m-d'),
                'to' => $window->checkOut()->format('Y-m-d'),
                'unavailable_ranges' => array_map(
                    static fn (StayPeriod $period): array => [
                        'check_in' => $period->checkIn()->format('Y-m-d'),
                        'check_out' => $period->checkOut()->format('Y-m-d'),
                    ],
                    $ranges,
                ),
            ];
        } catch (AvailabilityException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AvailabilityException::failure($failure);
        }
    }

    /**
     * @param array<string, mixed> $public
     * @return array<string, mixed>
     */
    private function mapCatalogRoom(int $roomId, array $public, int $nights, bool $available): array
    {
        return [
            'room_id' => $roomId,
            'name' => $public['name'],
            'slug' => $public['slug'],
            'is_available' => $available,
            'currency' => Booking::CURRENCY_NPR,
            'price_per_night' => $public['pricePerNight'],
            'room_type_name' => $public['roomTypeName'],
            'capacity_adults' => $public['capacityAdults'],
            'capacity_children' => $public['capacityChildren'],
            'primary_image_url' => $public['primaryImageUrl'],
            'amenities' => array_slice($public['amenities'], 0, 4),
            'nights' => $nights,
        ];
    }

    /** @return array<string, mixed> */
    private function map(Room $room, bool $available, int $nights): array
    {
        return [
            'room_id' => $room->id(),
            'name' => $room->name(),
            'slug' => $room->slug(),
            'is_available' => $available,
            'currency' => Booking::CURRENCY_NPR,
            'price_per_night' => $room->pricePerNight(),
            'room_type_name' => $room->roomType()->name(),
            'capacity_adults' => $room->capacityAdults(),
            'capacity_children' => $room->capacityChildren(),
            'primary_image_url' => '/assets/images/room-placeholder.svg',
            'amenities' => [],
            'nights' => $nights,
        ];
    }

    /**
     * @param list<StayPeriod> $periods
     * @return list<StayPeriod>
     */
    private function mergeAndClip(array $periods, StayPeriod $window): array
    {
        $merged = [];

        foreach ($periods as $period) {
            if (!$period instanceof StayPeriod || !$period->overlaps($window)) {
                continue;
            }

            $start = $period->checkIn() < $window->checkIn() ? $window->checkIn() : $period->checkIn();
            $end = $period->checkOut() > $window->checkOut() ? $window->checkOut() : $period->checkOut();
            $last = $merged === [] ? null : $merged[array_key_last($merged)];

            if ($last instanceof StayPeriod && $start <= $last->checkOut()) {
                $merged[array_key_last($merged)] = new StayPeriod(
                    $last->checkIn(),
                    $end > $last->checkOut() ? $end : $last->checkOut(),
                );
                continue;
            }

            $merged[] = new StayPeriod($start, $end);
        }

        return $merged;
    }
}
