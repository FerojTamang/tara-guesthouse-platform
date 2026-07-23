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

    private const ROOM_IMAGE_ERROR_KEY = 'administrator_room_image_error';

    private const ROOM_IMAGE_OLD_INPUT_KEY = 'administrator_room_image_old_input';

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

    public function roomImageError(string $message): void
    {
        $this->session->set(self::ROOM_IMAGE_ERROR_KEY, $message);
    }

    public function pullRoomImageError(): ?string
    {
        return $this->pull(self::ROOM_IMAGE_ERROR_KEY);
    }

    /** @param array{alt_text: string, sort_order: string, is_primary: string} $input */
    public function roomImageOldInput(array $input): void
    {
        $this->session->set(self::ROOM_IMAGE_OLD_INPUT_KEY, $input);
    }

    /** @return array{alt_text: string, sort_order: string, is_primary: string}|null */
    public function pullRoomImageOldInput(): ?array
    {
        $input = $this->session->pull(self::ROOM_IMAGE_OLD_INPUT_KEY);

        if (
            !is_array($input)
            || !isset($input['alt_text'], $input['sort_order'], $input['is_primary'])
            || !is_string($input['alt_text'])
            || !is_string($input['sort_order'])
            || !is_string($input['is_primary'])
        ) {
            return null;
        }

        return [
            'alt_text' => $input['alt_text'],
            'sort_order' => $input['sort_order'],
            'is_primary' => $input['is_primary'],
        ];
    }

    private function pull(string $key): ?string
    {
        $message = $this->session->pull($key);

        return is_string($message) ? $message : null;
    }
}
