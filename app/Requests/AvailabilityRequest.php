<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AvailabilityException;
use App\Models\StayPeriod;
use DateTimeImmutable;

/**
 * Holds one validated and normalized availability search request.
 */
final readonly class AvailabilityRequest
{
    private function __construct(
        private StayPeriod $stayPeriod,
        private int $adults,
        private int $children,
    ) {
    }

    public static function fromInput(
        mixed $checkIn,
        mixed $checkOut,
        mixed $adults,
        mixed $children,
    ): self {
        $normalizedCheckIn = AvailabilityInput::date($checkIn, 'Check-in date');
        $normalizedCheckOut = AvailabilityInput::date($checkOut, 'Check-out date');

        if ($normalizedCheckOut <= $normalizedCheckIn) {
            throw AvailabilityException::invalidInput(
                'Check-out date must be later than check-in date.',
            );
        }

        return new self(
            new StayPeriod($normalizedCheckIn, $normalizedCheckOut),
            AvailabilityInput::positiveInteger($adults, 'Adults'),
            AvailabilityInput::nonNegativeInteger($children, 'Children'),
        );
    }

    public function stayPeriod(): StayPeriod
    {
        return $this->stayPeriod;
    }

    public function checkIn(): DateTimeImmutable
    {
        return $this->stayPeriod->checkIn();
    }

    public function checkOut(): DateTimeImmutable
    {
        return $this->stayPeriod->checkOut();
    }

    public function nights(): int
    {
        return $this->stayPeriod->nights();
    }

    public function adults(): int
    {
        return $this->adults;
    }

    public function children(): int
    {
        return $this->children;
    }

    public function totalGuests(): int
    {
        return $this->adults + $this->children;
    }
}
