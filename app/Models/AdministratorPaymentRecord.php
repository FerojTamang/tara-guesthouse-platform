<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Aggregates one retained payment attempt with its booking and method snapshots.
 */
final readonly class AdministratorPaymentRecord
{
    public function __construct(
        private Payment $payment,
        private string $bookingReference,
        private string $guestFullName,
        private string $guestEmail,
        private string $guestPhone,
        private DateTimeImmutable $checkIn,
        private DateTimeImmutable $checkOut,
        private string $bookingStatus,
        private string $bookingTotalAmount,
        private DateTimeImmutable $confirmationDeadlineAt,
        private string $paymentMethodName,
        private ?string $paymentMethodAccountReference,
        private bool $paymentMethodRequiresProof,
        private bool $paymentMethodActive,
    ) {
        if (
            !$this->validText($bookingReference, 30)
            || !$this->validText($guestFullName, 100)
            || !$this->validText($guestEmail, 150)
            || !$this->validText($guestPhone, 20)
            || $checkIn->getOffset() !== 0
            || $checkOut->getOffset() !== 0
            || $checkOut <= $checkIn
            || !Booking::isStatus($bookingStatus)
            || !$this->positiveMoney($bookingTotalAmount)
            || $confirmationDeadlineAt->getOffset() !== 0
            || !$this->validText($paymentMethodName, 100)
            || (
                $paymentMethodAccountReference !== null
                && !$this->validOptionalText($paymentMethodAccountReference, 255)
            )
        ) {
            throw new InvalidArgumentException('Administrator payment record is invalid.');
        }
    }

    public function payment(): Payment
    {
        return $this->payment;
    }

    public function bookingReference(): string
    {
        return $this->bookingReference;
    }

    public function guestFullName(): string
    {
        return $this->guestFullName;
    }

    public function guestEmail(): string
    {
        return $this->guestEmail;
    }

    public function guestPhone(): string
    {
        return $this->guestPhone;
    }

    public function checkIn(): DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function checkOut(): DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function bookingStatus(): string
    {
        return $this->bookingStatus;
    }

    public function bookingTotalAmount(): string
    {
        return $this->bookingTotalAmount;
    }

    public function confirmationDeadlineAt(): DateTimeImmutable
    {
        return $this->confirmationDeadlineAt;
    }

    public function paymentMethodName(): string
    {
        return $this->paymentMethodName;
    }

    public function paymentMethodAccountReference(): ?string
    {
        return $this->paymentMethodAccountReference;
    }

    public function paymentMethodRequiresProof(): bool
    {
        return $this->paymentMethodRequiresProof;
    }

    public function paymentMethodActive(): bool
    {
        return $this->paymentMethodActive;
    }

    private function validText(string $value, int $maximumCharacters): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumCharacters;
    }

    private function validOptionalText(string $value, int $maximumCharacters): bool
    {
        return mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumCharacters;
    }

    private function positiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
