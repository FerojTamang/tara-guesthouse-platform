<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\BookingSubmissionResult;
use App\Requests\GuestBookingRequest;
use Throwable;

final readonly class BookingSubmissionWorkflow
{
    public function __construct(
        private BookingSubmitter $bookings,
        private BookingAccessToken $tokens,
    ) {
    }

    public function submit(GuestBookingRequest $request): BookingSubmissionResult
    {
        try {
            $booking = $this->bookings->submit($request);
            $deadline = $booking->confirmationDeadlineAt();

            if ($deadline === null) {
                throw BookingException::failure();
            }

            return new BookingSubmissionResult(
                $booking,
                $this->tokens->issue($booking->bookingReference(), $deadline),
            );
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
