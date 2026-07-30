<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * Reports whether an invoice was newly issued or already existed.
 */
final readonly class InvoiceIssueResult
{
    public function __construct(
        private Invoice $invoice,
        private bool $created,
    ) {
        if ($invoice->id() <= 0) {
            throw new InvalidArgumentException('Invoice issue result is invalid.');
        }
    }

    public function invoice(): Invoice
    {
        return $this->invoice;
    }

    public function created(): bool
    {
        return $this->created;
    }
}
