<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorInvoiceException;
use App\Models\AdministratorInvoiceRecord;
use App\Models\AdministratorInvoiceSummary;
use App\Models\Invoice;
use App\Repositories\AdministratorInvoiceCatalogRepository;
use Throwable;

/**
 * Provides read-only Administrator invoice list and detail workflows.
 */
final readonly class AdministratorInvoiceService
{
    public function __construct(
        private AdministratorInvoiceCatalogRepository $invoices,
    ) {
    }

    /** @return list<AdministratorInvoiceSummary> */
    public function list(): array
    {
        try {
            return $this->invoices->findAll();
        } catch (Throwable $failure) {
            throw AdministratorInvoiceException::failure($failure);
        }
    }

    public function detail(int $invoiceId): ?AdministratorInvoiceRecord
    {
        if ($invoiceId <= 0) {
            return null;
        }

        try {
            return $this->invoices->findById($invoiceId);
        } catch (Throwable $failure) {
            throw AdministratorInvoiceException::failure($failure);
        }
    }

    public function findByBookingId(int $bookingId): ?Invoice
    {
        if ($bookingId <= 0) {
            return null;
        }

        try {
            return $this->invoices->findInvoiceByBookingId($bookingId);
        } catch (Throwable $failure) {
            throw AdministratorInvoiceException::failure($failure);
        }
    }
}
