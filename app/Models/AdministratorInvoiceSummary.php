<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * Aggregates an issued invoice with safe booking list context.
 */
final readonly class AdministratorInvoiceSummary
{
    public function __construct(
        private Invoice $invoice,
        private string $bookingReference,
        private string $guestFullName,
    ) {
        if (
            !$this->validText($bookingReference, 30)
            || !$this->validText($guestFullName, 100)
        ) {
            throw new InvalidArgumentException('Administrator invoice summary is invalid.');
        }
    }

    public function invoice(): Invoice
    {
        return $this->invoice;
    }

    public function bookingReference(): string
    {
        return $this->bookingReference;
    }

    public function guestFullName(): string
    {
        return $this->guestFullName;
    }

    private function validText(string $value, int $maximum): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximum;
    }
}
