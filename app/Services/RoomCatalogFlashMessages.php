<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/**
 * Stores one safe room-catalogue success message for the next Administrator request.
 */
final readonly class RoomCatalogFlashMessages
{
    private const ROOM_TYPE_SUCCESS_KEY = 'administrator_room_type_success';

    public function __construct(private Session $session)
    {
    }

    public function roomTypeSuccess(string $message): void
    {
        $this->session->set(self::ROOM_TYPE_SUCCESS_KEY, $message);
    }

    public function pullRoomTypeSuccess(): ?string
    {
        $message = $this->session->pull(self::ROOM_TYPE_SUCCESS_KEY);

        return is_string($message) ? $message : null;
    }
}
