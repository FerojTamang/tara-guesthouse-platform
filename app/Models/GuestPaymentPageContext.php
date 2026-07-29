<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents the validated server-side facts needed to render a payment form.
 */
final readonly class GuestPaymentPageContext
{
    /** @param list<PaymentMethod> $paymentMethods */
    public function __construct(
        private Booking $booking,
        private array $paymentMethods,
        private DateTimeImmutable $validatedAt,
    ) {
        if (
            $booking->bookingStatus() !== Booking::STATUS_PENDING
            || $booking->currency() !== Booking::CURRENCY_NPR
            || $validatedAt->getOffset() !== 0
            || $validatedAt >= $booking->confirmationDeadlineAt()
        ) {
            throw new InvalidArgumentException('Guest payment page context is invalid.');
        }

        $identifiers = [];

        foreach ($paymentMethods as $paymentMethod) {
            if (
                !$paymentMethod instanceof PaymentMethod
                || !$paymentMethod->isActive()
                || isset($identifiers[$paymentMethod->id()])
            ) {
                throw new InvalidArgumentException('Guest payment page context is invalid.');
            }

            $identifiers[$paymentMethod->id()] = true;
        }
    }

    public function booking(): Booking
    {
        return $this->booking;
    }

    /** @return list<PaymentMethod> */
    public function paymentMethods(): array
    {
        return $this->paymentMethods;
    }

    public function validatedAt(): DateTimeImmutable
    {
        return $this->validatedAt;
    }
}
