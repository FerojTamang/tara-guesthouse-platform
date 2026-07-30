<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AdministratorInvoiceException;
use App\Models\AdministratorInvoiceRecord;
use App\Models\AdministratorInvoiceSummary;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Repositories\AdministratorInvoiceCatalogRepository;
use App\Services\AdministratorInvoiceService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorInvoiceServiceTest extends TestCase
{
    public function testItReturnsCatalogResultsAndRejectsInvalidIdentifiers(): void
    {
        $repository = new AdministratorInvoiceCatalogRepositoryFake();
        $repository->summaries = [self::summary()];
        $repository->record = self::record();
        $repository->invoice = self::invoice();
        $service = new AdministratorInvoiceService($repository);

        self::assertSame($repository->summaries, $service->list());
        self::assertSame($repository->record, $service->detail(51));
        self::assertSame($repository->invoice, $service->findByBookingId(31));
        self::assertNull($service->detail(0));
        self::assertNull($service->findByBookingId(-1));
        self::assertSame([51], $repository->detailIds);
        self::assertSame([31], $repository->bookingIds);
    }

    public function testRepositoryFailuresAreSanitized(): void
    {
        $repository = new AdministratorInvoiceCatalogRepositoryFake();
        $repository->fail = true;
        $service = new AdministratorInvoiceService($repository);

        try {
            $service->list();
            self::fail('Expected sanitized repository failure.');
        } catch (AdministratorInvoiceException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Administrator invoice operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQL secret', $exception->getMessage());
        }
    }

    private static function summary(): AdministratorInvoiceSummary
    {
        return new AdministratorInvoiceSummary(
            self::invoice(),
            'TGH-2026-INVOICE000000001',
            'Invoice Guest',
        );
    }

    private static function record(): AdministratorInvoiceRecord
    {
        return new AdministratorInvoiceRecord(
            self::invoice(),
            [new InvoiceItem(61, 51, 'Accommodation', '3.00', '6000.00', '18000.00')],
            'TGH-2026-INVOICE000000001',
            'Invoice Guest',
            'invoice@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            Booking::STATUS_CONFIRMED,
            44,
            'Bank Transfer',
            'BANK-44',
            new DateTimeImmutable('2026-07-30 04:00:00 UTC'),
            'Invoice Administrator',
        );
    }

    private static function invoice(): Invoice
    {
        $issuedAt = new DateTimeImmutable('2026-07-30 05:00:00 UTC');

        return new Invoice(
            51,
            31,
            'TGH-INV-2026-000051',
            Invoice::CURRENCY_NPR,
            '18000.00',
            Invoice::ZERO_TAX,
            '18000.00',
            $issuedAt,
            91,
            $issuedAt,
            $issuedAt,
        );
    }
}

final class AdministratorInvoiceCatalogRepositoryFake implements AdministratorInvoiceCatalogRepository
{
    /** @var list<AdministratorInvoiceSummary> */
    public array $summaries = [];
    public ?AdministratorInvoiceRecord $record = null;
    public ?Invoice $invoice = null;
    /** @var list<int> */
    public array $detailIds = [];
    /** @var list<int> */
    public array $bookingIds = [];
    public bool $fail = false;

    public function findAll(): array
    {
        if ($this->fail) {
            throw new RuntimeException('SQL secret');
        }

        return $this->summaries;
    }

    public function findById(int $invoiceId): ?AdministratorInvoiceRecord
    {
        $this->detailIds[] = $invoiceId;

        return $this->record;
    }

    public function findInvoiceByBookingId(int $bookingId): ?Invoice
    {
        $this->bookingIds[] = $bookingId;

        return $this->invoice;
    }
}
