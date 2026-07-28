<?php

declare(strict_types=1);

namespace App\Models;

use App\Requests\GuestBookingRequest;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BookingQuote
{
    public function __construct(
        private GuestBookingRequest $request,
        private Room $room,
        private string $currency,
        private string $pricePerNight,
        private int $nights,
        private string $roomAmount,
        private string $totalAmount,
        private DateTimeImmutable $checkedAt,
    ) {
        if (
            $request->roomId() !== $room->id()
            || !$room->isActive()
            || !$room->isPublished()
            || $room->isDeleted()
            || $request->adults() > $room->capacityAdults()
            || $request->children() > $room->capacityChildren()
            || $currency !== Booking::CURRENCY_NPR
            || $pricePerNight !== $room->pricePerNight()
            || !$this->isPositiveMoney($pricePerNight)
            || $nights !== $request->stayPeriod()->nights()
            || $nights < 1
            || !$this->isPositiveMoney($roomAmount)
            || !$this->isPositiveMoney($totalAmount)
            || $roomAmount !== $totalAmount
            || $checkedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Booking quote data is invalid.');
        }
    }

    public function request(): GuestBookingRequest
    {
        return $this->request;
    }

    public function room(): Room
    {
        return $this->room;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function pricePerNight(): string
    {
        return $this->pricePerNight;
    }

    public function nights(): int
    {
        return $this->nights;
    }

    public function roomAmount(): string
    {
        return $this->roomAmount;
    }

    public function totalAmount(): string
    {
        return $this->totalAmount;
    }

    public function checkedAt(): DateTimeImmutable
    {
        return $this->checkedAt;
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
