<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Models\Payment;
use SensitiveParameter;

/** Disables SMTP delivery without changing committed domain workflows. */
final readonly class NullTransactionalMailDispatcher implements TransactionalMailDispatcher
{
    public function bookingReceived(Booking $booking): void
    {
    }

    public function bookingStatusChanged(Booking $booking, string $status): void
    {
    }

    public function paymentSubmitted(Booking $booking, Payment $payment): void
    {
    }

    public function paymentReviewed(Booking $booking, Payment $payment): void
    {
    }

    public function reviewInvitation(
        Booking $booking,
        #[SensitiveParameter] IssuedReviewInvitation $invitation,
    ): void {
    }
}
