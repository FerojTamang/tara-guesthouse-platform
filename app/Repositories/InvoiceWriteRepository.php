<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use DateTimeImmutable;

/**
 * Defines invoice issue reads and writes inside a caller-owned transaction.
 */
interface InvoiceWriteRepository
{
    public function lockBooking(int $bookingId): ?Booking;

    public function lockRoom(int $bookingId): ?BookingRoom;

    /** @return list<Payment> */
    public function lockVerifiedPayments(int $bookingId): array;

    public function findInvoiceByBookingId(int $bookingId): ?Invoice;

    public function createInvoice(
        int $bookingId,
        string $currency,
        string $subtotal,
        string $taxAmount,
        string $totalAmount,
        DateTimeImmutable $issuedAt,
        int $createdBy,
        int $invoiceYear,
    ): Invoice;

    public function appendItem(
        int $invoiceId,
        string $description,
        string $quantity,
        string $unitPrice,
        string $lineTotal,
    ): InvoiceItem;
}
