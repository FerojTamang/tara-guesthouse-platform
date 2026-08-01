<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailException;
use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Repositories\BookingCatalogRepository;
use SensitiveParameter;
use Throwable;

/** Resolves the checked-out booking and dispatches its review invitation after commit. */
final readonly class ReviewInvitationMailDelivery implements ReviewInvitationDelivery
{
    public function __construct(
        private BookingCatalogRepository $bookings,
        private TransactionalMailDispatcher $mail,
    ) {
    }

    public function deliver(#[SensitiveParameter] IssuedReviewInvitation $invitation): void
    {
        try {
            $booking = $this->bookings->findById($invitation->invitation()->bookingId());

            if (!$booking instanceof Booking) {
                throw MailException::failure();
            }

            $this->mail->reviewInvitation($booking, $invitation);
        } catch (MailException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw MailException::failure();
        }
    }
}
