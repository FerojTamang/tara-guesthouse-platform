<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents the one-room assignment and historical price snapshot for a Version 1 booking.
 */
final readonly class BookingRoom
{
    public function __construct(
        private int $id,
        private int $bookingId,
        private int $roomId,
        private string $pricePerNight,
        private int $nights,
        private string $subtotal,
        private DateTimeImmutable $createdAt,
    ) {
        if (
            $id <= 0
            || $bookingId <= 0
            || $roomId <= 0
            || !$this->isPositiveMoney($pricePerNight)
            || $nights < 1
            || !$this->isPositiveMoney($subtotal)
            || $createdAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Booking room data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function roomId(): int
    {
        return $this->roomId;
    }

    public function pricePerNight(): string
    {
        return $this->pricePerNight;
    }

    public function nights(): int
    {
        return $this->nights;
    }

    public function subtotal(): string
    {
        return $this->subtotal;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
