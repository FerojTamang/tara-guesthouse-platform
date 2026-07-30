<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Aggregates one invoice with immutable line items and review context.
 */
final readonly class AdministratorInvoiceRecord
{
    /** @param non-empty-list<InvoiceItem> $items */
    public function __construct(
        private Invoice $invoice,
        private array $items,
        private string $bookingReference,
        private string $guestFullName,
        private string $guestEmail,
        private string $guestPhone,
        private DateTimeImmutable $checkIn,
        private DateTimeImmutable $checkOut,
        private string $bookingStatus,
        private int $paymentId,
        private string $paymentMethodName,
        private ?string $transactionReference,
        private DateTimeImmutable $verifiedAt,
        private string $createdByName,
    ) {
        if (
            $items === []
            || !$this->validText($bookingReference, 30)
            || !$this->validText($guestFullName, 100)
            || !$this->validText($guestEmail, 150)
            || !$this->validText($guestPhone, 20)
            || $checkIn->getOffset() !== 0
            || $checkOut->getOffset() !== 0
            || $checkOut <= $checkIn
            || !Booking::isStatus($bookingStatus)
            || $paymentId <= 0
            || !$this->validText($paymentMethodName, 100)
            || (
                $transactionReference !== null
                && (!$this->validOptionalText($transactionReference, 100))
            )
            || $verifiedAt->getOffset() !== 0
            || !$this->validText($createdByName, 100)
        ) {
            throw new InvalidArgumentException('Administrator invoice record is invalid.');
        }

        foreach ($items as $item) {
            if (
                !$item instanceof InvoiceItem
                || $item->invoiceId() !== $invoice->id()
            ) {
                throw new InvalidArgumentException('Administrator invoice record is invalid.');
            }
        }
    }

    public function invoice(): Invoice
    {
        return $this->invoice;
    }

    /** @return non-empty-list<InvoiceItem> */
    public function items(): array
    {
        return $this->items;
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

    public function paymentId(): int
    {
        return $this->paymentId;
    }

    public function paymentMethodName(): string
    {
        return $this->paymentMethodName;
    }

    public function transactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function verifiedAt(): DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function createdByName(): string
    {
        return $this->createdByName;
    }

    private function validText(string $value, int $maximum): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximum;
    }

    private function validOptionalText(string $value, int $maximum): bool
    {
        return mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximum;
    }
}
