<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reports one committed payment review without confidential payment evidence.
 */
final readonly class PaymentStatusTransitionResult
{
    public function __construct(
        private int $paymentId,
        private int $bookingId,
        private string $fromStatus,
        private string $toStatus,
        private DateTimeImmutable $changedAt,
    ) {
        if (
            $paymentId <= 0
            || $bookingId <= 0
            || !Payment::isStatus($fromStatus)
            || !in_array(
                $toStatus,
                [Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED],
                true,
            )
            || $fromStatus === $toStatus
            || $changedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Payment status transition result is invalid.');
        }
    }

    public function paymentId(): int
    {
        return $this->paymentId;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function fromStatus(): string
    {
        return $this->fromStatus;
    }

    public function toStatus(): string
    {
        return $this->toStatus;
    }

    public function changedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }
}
