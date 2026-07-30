<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable issued invoice financial snapshot.
 */
final readonly class Invoice
{
    public const CURRENCY_NPR = 'NPR';
    public const ZERO_TAX = '0.00';

    public function __construct(
        private int $id,
        private int $bookingId,
        private string $invoiceNumber,
        private string $currency,
        private string $subtotal,
        private string $taxAmount,
        private string $totalAmount,
        private DateTimeImmutable $issuedAt,
        private int $createdBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $bookingId <= 0
            || preg_match('/^TGH-INV-[0-9]{4}-[0-9]{6}$/D', $invoiceNumber) !== 1
            || $currency !== self::CURRENCY_NPR
            || !$this->positiveMoney($subtotal)
            || $taxAmount !== self::ZERO_TAX
            || !$this->positiveMoney($totalAmount)
            || $subtotal !== $totalAmount
            || $issuedAt->getOffset() !== 0
            || $createdBy <= 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Invoice data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function subtotal(): string
    {
        return $this->subtotal;
    }

    public function taxAmount(): string
    {
        return $this->taxAmount;
    }

    public function totalAmount(): string
    {
        return $this->totalAmount;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function positiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
