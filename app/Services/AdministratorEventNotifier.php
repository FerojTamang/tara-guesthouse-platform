<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\Review;

/** Defines post-commit Administrator in-app notifications for Guest-originated events. */
interface AdministratorEventNotifier
{
    public function bookingReceived(Booking $booking): void;

    public function contactMessageReceived(ContactMessage $contact): void;

    public function paymentSubmitted(Booking $booking, Payment $payment): void;

    public function reviewSubmitted(Review $review): void;
}
