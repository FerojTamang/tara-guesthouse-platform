<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Repositories\BookingWriteRepository;
use App\Repositories\RoomCatalogRepository;
use App\Requests\GuestBookingRequest;
use DateTimeImmutable;
use Throwable;

/**
 * Creates one pending booking while serializing competing requests for the selected room.
 */
final readonly class BookingCreationService
{
    public function __construct(
        private RoomCatalogRepository $rooms,
        private BookingService $booking,
        private BookingWriteRepository $writer,
        private BookingTransaction $transaction,
    ) {
    }

    public function createPending(
        GuestBookingRequest $request,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): Booking {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();

            $lockedRoom = $this->rooms->lockCurrentById($request->roomId());

            if (!$lockedRoom instanceof Room) {
                throw BookingException::roomNotFound();
            }

            $quote = $this->booking->prepare($request);
            $created = $this->writer->createPending(
                $quote,
                $bookingReference,
                $confirmationDeadlineAt,
            );

            $commitAttempted = true;
            $this->transaction->commit();

            return $created;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof BookingException
                ? $failure
                : BookingException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw BookingException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw BookingException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw BookingException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw BookingException::failure();
            }
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
