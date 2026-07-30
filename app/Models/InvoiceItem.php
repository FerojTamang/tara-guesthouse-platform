<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * Represents one immutable invoice line-item snapshot.
 */
final readonly class InvoiceItem
{
    public function __construct(
        private int $id,
        private int $invoiceId,
        private string $description,
        private string $quantity,
        private string $unitPrice,
        private string $lineTotal,
    ) {
        if (
            $id <= 0
            || $invoiceId <= 0
            || $description === ''
            || $description !== trim($description)
            || !mb_check_encoding($description, 'UTF-8')
            || mb_strlen($description, 'UTF-8') > 255
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $description) === 1
            || !$this->positiveDecimal($quantity, 8)
            || !$this->positiveDecimal($unitPrice, 10)
            || !$this->positiveDecimal($lineTotal, 10)
        ) {
            throw new InvalidArgumentException('Invoice item data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function invoiceId(): int
    {
        return $this->invoiceId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): string
    {
        return $this->quantity;
    }

    public function unitPrice(): string
    {
        return $this->unitPrice;
    }

    public function lineTotal(): string
    {
        return $this->lineTotal;
    }

    private function positiveDecimal(string $value, int $integerDigits): bool
    {
        return preg_match(
            '/^(?:0|[1-9][0-9]{0,' . ($integerDigits - 1) . '})\.[0-9]{2}$/D',
            $value,
        ) === 1 && $value !== '0.00';
    }
}
