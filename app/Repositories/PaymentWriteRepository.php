<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use DateTimeImmutable;

/**
 * Defines payment writes executed inside a caller-owned transaction.
 */
interface PaymentWriteRepository
{
    public function lockBookingByReference(string $bookingReference): ?Booking;

    public function lockPaymentMethodById(int $paymentMethodId): ?PaymentMethod;

    public function lockCurrent(int $paymentId): ?Payment;

    public function createSubmitted(
        int $bookingId,
        int $paymentMethodId,
        string $amount,
        ?string $transactionReference,
        ?string $proofPath,
        ?string $guestNotes,
        DateTimeImmutable $submittedAt,
    ): Payment;

    /**
     * The caller must already hold the associated booking row lock.
     */
    public function hasVerifiedForBooking(
        int $bookingId,
        ?int $excludingPaymentId = null,
    ): bool;

    public function markVerified(
        Payment $current,
        int $verifiedBy,
        string $note,
        DateTimeImmutable $verifiedAt,
    ): Payment;

    public function markRejected(
        Payment $current,
        int $rejectedBy,
        string $note,
        DateTimeImmutable $rejectedAt,
    ): Payment;
}
