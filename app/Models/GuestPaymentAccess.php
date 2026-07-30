<?php

declare(strict_types=1);

namespace App\Models;

use App\Requests\GuestPaymentRequest;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one non-transactional Guest payment access validation result.
 *
 * The submission service must lock and recheck all mutable booking and method
 * facts before writing a payment attempt.
 */
final readonly class GuestPaymentAccess
{
    public function __construct(
        private GuestPaymentRequest $request,
        private Booking $booking,
        private PaymentMethod $paymentMethod,
        private DateTimeImmutable $validatedAt,
    ) {
        if (
            $request->bookingReference() !== $booking->bookingReference()
            || $request->paymentMethodId() !== $paymentMethod->id()
            || $request->currency() !== Booking::CURRENCY_NPR
            || $booking->currency() !== Booking::CURRENCY_NPR
            || $request->amount() !== $booking->totalAmount()
            || $booking->bookingStatus() !== Booking::STATUS_PENDING
            || !$paymentMethod->isActive()
            || $validatedAt->getOffset() !== 0
            || $validatedAt >= $booking->confirmationDeadlineAt()
            || ($paymentMethod->requiresProof() && !$request->hasProof())
        ) {
            throw new InvalidArgumentException('Guest payment access data is invalid.');
        }
    }

    public function request(): GuestPaymentRequest
    {
        return $this->request;
    }

    public function booking(): Booking
    {
        return $this->booking;
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function validatedAt(): DateTimeImmutable
    {
        return $this->validatedAt;
    }
}
