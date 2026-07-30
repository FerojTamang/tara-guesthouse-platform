<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AdministratorInvoiceRecord;
use App\Models\AdministratorInvoiceSummary;
use App\Models\Invoice;

/**
 * Defines authenticated Administrator invoice reads.
 */
interface AdministratorInvoiceCatalogRepository
{
    /** @return list<AdministratorInvoiceSummary> */
    public function findAll(): array;

    public function findById(int $invoiceId): ?AdministratorInvoiceRecord;

    public function findInvoiceByBookingId(int $bookingId): ?Invoice;
}
