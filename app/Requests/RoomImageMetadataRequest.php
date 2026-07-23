<?php

declare(strict_types=1);

namespace App\Requests;

final readonly class RoomImageMetadataRequest
{
    private function __construct(
        private int $roomId,
        private int $imageId,
        private ?string $alternativeText,
        private int $sortOrder,
    ) {
    }

    public static function fromInput(
        mixed $roomId,
        mixed $imageId,
        mixed $alternativeText,
        mixed $sortOrder,
    ): self {
        $order = RoomImageInput::sortOrder($sortOrder, true);

        return new self(
            RoomCatalogInput::identifier($roomId, 'Room'),
            RoomCatalogInput::identifier($imageId, 'Room image'),
            RoomImageInput::alternativeText($alternativeText),
            $order,
        );
    }

    public function roomId(): int
    {
        return $this->roomId;
    }

    public function imageId(): int
    {
        return $this->imageId;
    }

    public function alternativeText(): ?string
    {
        return $this->alternativeText;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }
}
