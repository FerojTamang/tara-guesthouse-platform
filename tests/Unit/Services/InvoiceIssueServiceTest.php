<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AdministratorInvoiceException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\InvoiceWriteRepository;
use App\Services\BookingTransaction;
use App\Services\InvoiceIssueService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvoiceIssueServiceTest extends TestCase
{
    private InvoiceIssueRepositoryFake $invoices;
    private InvoiceIssueAuditFake $audit;
    private InvoiceIssueTransactionFake $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFixtures();
    }

    private function resetFixtures(): void
    {
        $this->invoices = new InvoiceIssueRepositoryFake();
        $this->invoices->booking = self::booking();
        $this->invoices->room = self::room();
        $this->invoices->payments = [self::payment()];
        $this->audit = new InvoiceIssueAuditFake();
        $this->transaction = new InvoiceIssueTransactionFake();
    }

    public function testItIssuesOneSnapshotAndAuditInsideOneTransaction(): void
    {
        $service = $this->service(new DateTimeImmutable('2026-12-31 20:30:00 UTC'));

        $result = $service->issue(
            31,
            91,
            '203.0.113.70',
            'Tara Invoice Agent',
        );

        self::assertTrue($result->created());
        self::assertSame('TGH-INV-2027-000051', $result->invoice()->invoiceNumber());
        self::assertSame([
            'booking:31',
            'existing:31',
            'room:31',
            'payments:31',
            'create:31:2027',
            'item:51',
        ], $this->invoices->operations);
        self::assertSame('Accommodation', $this->invoices->itemDescription);
        self::assertSame('3.00', $this->invoices->itemQuantity);
        self::assertSame('6000.00', $this->invoices->itemUnitPrice);
        self::assertSame('18000.00', $this->invoices->itemLineTotal);
        self::assertSame(1, $this->transaction->commitCount);
        self::assertSame(0, $this->transaction->rollbackCount);
        self::assertCount(1, $this->audit->entries);

        $entry = $this->audit->entries[0];
        self::assertSame('invoice_issued', $entry->eventType());
        self::assertSame('invoice', $entry->entityType());
        self::assertSame(51, $entry->entityId());
        self::assertSame(91, $entry->userId());
        self::assertSame('203.0.113.70', $entry->ipAddress());
        self::assertSame('Tara Invoice Agent', $entry->userAgent());
        self::assertSame([
            'booking_id' => 31,
            'payment_id' => 44,
            'invoice_number' => 'TGH-INV-2027-000051',
        ], json_decode(
            (string) $entry->metadataJson(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertStringNotContainsString('guest@example', (string) $entry->metadataJson());
        self::assertStringNotContainsString('payment-proofs', (string) $entry->metadataJson());
    }

    public function testRepeatedIssueReturnsStableExistingInvoiceWithoutNewAudit(): void
    {
        $this->invoices->existing = self::invoice();

        $result = $this->service()->issue(31, 91, null, null);

        self::assertFalse($result->created());
        self::assertSame('TGH-INV-2026-000051', $result->invoice()->invoiceNumber());
        self::assertSame(['booking:31', 'existing:31'], $this->invoices->operations);
        self::assertSame([], $this->audit->entries);
        self::assertSame(1, $this->transaction->commitCount);
        self::assertSame(0, $this->transaction->rollbackCount);
    }

    public function testMissingBookingReturnsNotFoundAndRollsBack(): void
    {
        $this->invoices->booking = null;

        try {
            $this->service()->issue(404, 91, null, null);
            self::fail('Expected missing booking failure.');
        } catch (AdministratorInvoiceException $exception) {
            self::assertTrue($exception->isNotFound());
        }

        self::assertSame(1, $this->transaction->rollbackCount);
        self::assertSame([], $this->audit->entries);
    }

    public function testEligibilityFailuresRollbackWithoutWrites(): void
    {
        $scenarios = [
            'booking not confirmed' => function (): void {
                $this->invoices->booking = self::booking(Booking::STATUS_PENDING);
            },
            'missing room' => function (): void {
                $this->invoices->room = null;
            },
            'missing payment' => function (): void {
                $this->invoices->payments = [];
            },
            'multiple verified payments' => function (): void {
                $this->invoices->payments = [self::payment(), self::payment(45)];
            },
            'payment amount mismatch' => function (): void {
                $this->invoices->payments = [self::payment(amount: '17000.00')];
            },
            'room snapshot mismatch' => function (): void {
                $this->invoices->room = self::room(subtotal: '17000.00');
            },
        ];

        foreach ($scenarios as $name => $configure) {
            $this->resetFixtures();
            $configure();

            try {
                $this->service()->issue(31, 91, null, null);
                self::fail('Expected invoice conflict for ' . $name);
            } catch (AdministratorInvoiceException $exception) {
                self::assertTrue($exception->isConflict(), $name);
            }

            self::assertNull($this->invoices->createdInvoice, $name);
            self::assertSame([], $this->audit->entries, $name);
            self::assertSame(1, $this->transaction->rollbackCount, $name);
        }
    }

    public function testAuditFailureRollsBackInvoiceAndItem(): void
    {
        $this->audit->fail = true;

        try {
            $this->service()->issue(31, 91, null, null);
            self::fail('Expected audit failure.');
        } catch (AdministratorInvoiceException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertNotNull($this->invoices->createdInvoice);
        self::assertSame(0, $this->transaction->commitCount);
        self::assertSame(1, $this->transaction->rollbackCount);
    }

    public function testExistingCallerTransactionIsRejectedWithoutRepositoryAccess(): void
    {
        $this->transaction->active = true;

        try {
            $this->service()->issue(31, 91, null, null);
            self::fail('Expected transaction ownership failure.');
        } catch (AdministratorInvoiceException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertSame([], $this->invoices->operations);
    }

    private function service(?DateTimeImmutable $now = null): InvoiceIssueService
    {
        $clock = $now ?? new DateTimeImmutable('2026-07-30 05:00:00 UTC');

        return new InvoiceIssueService(
            $this->invoices,
            $this->audit,
            $this->transaction,
            new DateTimeZone('Asia/Kathmandu'),
            static fn (): DateTimeImmutable => $clock,
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

    private static function booking(string $status = Booking::STATUS_CONFIRMED): Booking
    {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-INVOICE000000001',
            null,
            'Invoice Guest',
            'invoice@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            $status,
            null,
            $createdAt,
            'v1',
            new DateTimeImmutable('2026-07-30 05:00:00 UTC'),
            $status === Booking::STATUS_CONFIRMED
                ? new DateTimeImmutable('2026-07-30 04:00:00 UTC')
                : null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            null,
        );
    }

    private static function room(string $subtotal = '18000.00'): BookingRoom
    {
        return new BookingRoom(
            41,
            31,
            12,
            '6000.00',
            3,
            $subtotal,
            new DateTimeImmutable('2026-07-30 03:00:00 UTC'),
        );
    }

    private static function payment(int $id = 44, string $amount = '18000.00'): Payment
    {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Payment(
            $id,
            31,
            2,
            Payment::CURRENCY_NPR,
            $amount,
            'BANK-' . $id,
            null,
            null,
            Payment::STATUS_VERIFIED,
            new DateTimeImmutable('2026-07-30 03:10:00 UTC'),
            new DateTimeImmutable('2026-07-30 04:00:00 UTC'),
            91,
            null,
            null,
            'Verified.',
            $createdAt,
            $createdAt,
        );
    }
}

final class InvoiceIssueRepositoryFake implements InvoiceWriteRepository
{
    public ?Booking $booking = null;
    public ?BookingRoom $room = null;
    /** @var list<Payment> */
    public array $payments = [];
    public ?Invoice $existing = null;
    public ?Invoice $createdInvoice = null;
    /** @var list<string> */
    public array $operations = [];
    public ?string $itemDescription = null;
    public ?string $itemQuantity = null;
    public ?string $itemUnitPrice = null;
    public ?string $itemLineTotal = null;

    public function lockBooking(int $bookingId): ?Booking
    {
        $this->operations[] = 'booking:' . $bookingId;

        return $this->booking;
    }

    public function lockRoom(int $bookingId): ?BookingRoom
    {
        $this->operations[] = 'room:' . $bookingId;

        return $this->room;
    }

    public function lockVerifiedPayments(int $bookingId): array
    {
        $this->operations[] = 'payments:' . $bookingId;

        return $this->payments;
    }

    public function findInvoiceByBookingId(int $bookingId): ?Invoice
    {
        $this->operations[] = 'existing:' . $bookingId;

        return $this->existing;
    }

    public function createInvoice(
        int $bookingId,
        string $currency,
        string $subtotal,
        string $taxAmount,
        string $totalAmount,
        DateTimeImmutable $issuedAt,
        int $createdBy,
        int $invoiceYear,
    ): Invoice {
        $this->operations[] = sprintf('create:%d:%d', $bookingId, $invoiceYear);

        return $this->createdInvoice = new Invoice(
            51,
            $bookingId,
            sprintf('TGH-INV-%04d-000051', $invoiceYear),
            $currency,
            $subtotal,
            $taxAmount,
            $totalAmount,
            $issuedAt,
            $createdBy,
            $issuedAt,
            $issuedAt,
        );
    }

    public function appendItem(
        int $invoiceId,
        string $description,
        string $quantity,
        string $unitPrice,
        string $lineTotal,
    ): InvoiceItem {
        $this->operations[] = 'item:' . $invoiceId;
        $this->itemDescription = $description;
        $this->itemQuantity = $quantity;
        $this->itemUnitPrice = $unitPrice;
        $this->itemLineTotal = $lineTotal;

        return new InvoiceItem(
            61,
            $invoiceId,
            $description,
            $quantity,
            $unitPrice,
            $lineTotal,
        );
    }
}

final class InvoiceIssueAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public bool $fail = false;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->fail) {
            throw new RuntimeException('Audit write failed.');
        }

        $this->entries[] = $entry;
    }
}

final class InvoiceIssueTransactionFake implements BookingTransaction
{
    public bool $active = false;
    public int $commitCount = 0;
    public int $rollbackCount = 0;

    public function begin(): void
    {
        if ($this->active) {
            throw new RuntimeException('Transaction already active.');
        }

        $this->active = true;
    }

    public function commit(): void
    {
        if (!$this->active) {
            throw new RuntimeException('No transaction.');
        }

        $this->commitCount++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        if (!$this->active) {
            throw new RuntimeException('No transaction.');
        }

        $this->rollbackCount++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
