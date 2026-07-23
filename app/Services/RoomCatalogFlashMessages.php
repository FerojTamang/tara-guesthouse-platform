<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/**
 * Stores one safe room-catalogue success message for the next Administrator request.
 */
final readonly class RoomCatalogFlashMessages
{
    private const ROOM_SUCCESS_KEY = 'administrator_room_success';

    private const ROOM_TYPE_SUCCESS_KEY = 'administrator_room_type_success';

    private const AMENITY_SUCCESS_KEY = 'administrator_amenity_success';

    public function __construct(private Session $session)
    {
    }

    public function roomSuccess(string $message): void
    {
        $this->session->set(self::ROOM_SUCCESS_KEY, $message);
    }

    public function pullRoomSuccess(): ?string
    {
        return $this->pull(self::ROOM_SUCCESS_KEY);
    }

    public function roomTypeSuccess(string $message): void
    {
        $this->session->set(self::ROOM_TYPE_SUCCESS_KEY, $message);
    }

    public function pullRoomTypeSuccess(): ?string
    {
        return $this->pull(self::ROOM_TYPE_SUCCESS_KEY);
    }

    public function amenitySuccess(string $message): void
    {
        $this->session->set(self::AMENITY_SUCCESS_KEY, $message);
    }

    public function pullAmenitySuccess(): ?string
    {
        return $this->pull(self::AMENITY_SUCCESS_KEY);
    }

    private function pull(string $key): ?string
    {
        $message = $this->session->pull($key);

        return is_string($message) ? $message : null;
    }
}
