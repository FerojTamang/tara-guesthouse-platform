<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingQuote;
use App\Models\Room;
use App\Repositories\AvailabilityRepository;
use App\Repositories\RoomCatalogRepository;
use App\Requests\GuestBookingRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class BookingService
{
    private const MAX_MONEY_CENTS = 999999999999;

    /** @var Closure(): mixed */
    private readonly Closure $clock;

    /**
     * @param null|callable(): mixed $clock
     */
    public function __construct(
        private readonly RoomCatalogRepository $rooms,
        private readonly AvailabilityRepository $availability,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    public function prepare(GuestBookingRequest $request): BookingQuote
    {
        try {
            $checkedAt = ($this->clock)();

            if (!$checkedAt instanceof DateTimeImmutable || $checkedAt->getOffset() !== 0) {
                throw new RuntimeException('The booking clock returned invalid data.');
            }

            $room = $this->rooms->findById($request->roomId());

            if (!$room instanceof Room) {
                throw BookingException::roomNotFound();
            }

            if (
                !$room->isActive()
                || !$room->isPublished()
                || $room->isDeleted()
                || $request->adults() > $room->capacityAdults()
                || $request->children() > $room->capacityChildren()
            ) {
                throw BookingException::roomUnavailable();
            }

            if ($this->availability->hasBlockingOverlap(
                $room->id(),
                $request->stayPeriod(),
                $checkedAt,
            )) {
                throw BookingException::roomUnavailable();
            }

            $nights = $request->stayPeriod()->nights();
            $total = $this->multiplyMoney($room->pricePerNight(), $nights);

            return new BookingQuote(
                $request,
                $room,
                Booking::CURRENCY_NPR,
                $room->pricePerNight(),
                $nights,
                $total,
                $total,
                $checkedAt,
            );
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    private function multiplyMoney(string $amount, int $quantity): string
    {
        if (
            $quantity < 1
            || preg_match('/^([0-9]+)\.([0-9]{2})$/D', $amount, $matches) !== 1
        ) {
            throw new RuntimeException('The booking amount could not be calculated.');
        }

        $cents = ((int) $matches[1] * 100) + (int) $matches[2];

        if ($cents <= 0 || $cents > intdiv(self::MAX_MONEY_CENTS, $quantity)) {
            throw new RuntimeException('The booking amount could not be calculated.');
        }

        $totalCents = $cents * $quantity;

        return intdiv($totalCents, 100)
            . '.'
            . str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
