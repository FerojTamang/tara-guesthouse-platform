<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusTransitionResult;
use App\Models\Payment;
use DateTimeImmutable;

/**
 * Defines the Booking Service operation used after a payment is verified.
 */
interface VerifiedPaymentBookingConfirmer
{
    public function confirm(
        Booking $booking,
        Payment $verifiedPayment,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $confirmedAt,
    ): BookingStatusTransitionResult;
}
