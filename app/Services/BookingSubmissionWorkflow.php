<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSubmissionResult;
use App\Requests\GuestBookingRequest;
use Throwable;

final readonly class BookingSubmissionWorkflow
{
    public function __construct(
        private BookingSubmitter $bookings,
        private BookingAccessToken $tokens,
        private ?TransactionalMailDispatcher $mail = null,
        private ?AdministratorEventNotifier $notifications = null,
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

            $result = new BookingSubmissionResult(
                $booking,
                $this->tokens->issue($booking->bookingReference(), $deadline),
            );
            $this->deliverAdministratorNotification($booking);
            $this->deliverBookingReceived($booking);

            return $result;
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    private function deliverAdministratorNotification(Booking $booking): void
    {
        if (!$this->notifications instanceof AdministratorEventNotifier) {
            return;
        }

        try {
            $this->notifications->bookingReceived($booking);
        } catch (Throwable) {
            error_log('Booking post-commit Administrator notification delivery failed.');
        }
    }

    private function deliverBookingReceived(Booking $booking): void
    {
        if (!$this->mail instanceof TransactionalMailDispatcher) {
            return;
        }

        try {
            $this->mail->bookingReceived($booking);
        } catch (Throwable) {
            error_log('Booking post-commit email delivery failed.');
        }
    }
}
