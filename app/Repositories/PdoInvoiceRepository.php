<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\AdministratorInvoiceRecord;
use App\Models\AdministratorInvoiceSummary;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides strict invoice catalog reads and caller-transaction-owned issue writes.
 */
final class PdoInvoiceRepository implements AdministratorInvoiceCatalogRepository, InvoiceWriteRepository
{
    use MapsRepositoryRows;

    private const INVOICE_COLUMNS = <<<'SQL'
        SELECT
            id,
            booking_id,
            invoice_number,
            currency,
            subtotal,
            tax_amount,
            total_amount,
            issued_at,
            created_by,
            created_at,
            updated_at
        FROM invoices
        SQL;

    private const BOOKING_COLUMNS = <<<'SQL'
        SELECT
            id,
            booking_reference,
            user_id,
            guest_full_name,
            guest_email,
            guest_phone,
            check_in,
            check_out,
            adults,
            children,
            currency,
            room_amount,
            total_amount,
            booking_status,
            special_request,
            terms_accepted_at,
            policy_version,
            confirmation_deadline_at,
            confirmed_at,
            checked_in_at,
            checked_out_at,
            cancelled_at,
            cancellation_reason,
            rejected_at,
            rejection_reason,
            created_at,
            updated_at,
            deleted_at
        FROM bookings
        SQL;

    private const PAYMENT_COLUMNS = <<<'SQL'
        SELECT
            id,
            booking_id,
            payment_method_id,
            currency,
            amount,
            transaction_reference,
            proof_path,
            guest_notes,
            payment_status,
            submitted_at,
            verified_at,
            verified_by,
            rejected_at,
            rejected_by,
            verification_note,
            created_at,
            updated_at
        FROM payments
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return list<AdministratorInvoiceSummary> */
    public function findAll(): array
    {
        try {
            $statement = $this->connection->query(
                'SELECT '
                . 'i.id, i.booking_id, i.invoice_number, i.currency, '
                . 'i.subtotal, i.tax_amount, i.total_amount, i.issued_at, '
                . 'i.created_by, i.created_at, i.updated_at, '
                . 'b.booking_reference, b.guest_full_name '
                . 'FROM invoices i '
                . 'INNER JOIN bookings b ON b.id = i.booking_id '
                . 'ORDER BY i.issued_at DESC, i.id DESC',
            );
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('Invoice rows could not be mapped.');
            }

            $summaries = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('Invoice rows could not be mapped.');
                }

                $summaries[] = new AdministratorInvoiceSummary(
                    $this->mapInvoice($row),
                    $this->string($row, 'booking_reference'),
                    $this->string($row, 'guest_full_name'),
                );
            }

            return $summaries;
        } catch (Throwable) {
            throw new DatabaseException('The Administrator invoice listing failed.');
        }
    }

    public function findById(int $invoiceId): ?AdministratorInvoiceRecord
    {
        if ($invoiceId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'SELECT '
                . 'i.id, i.booking_id, i.invoice_number, i.currency, '
                . 'i.subtotal, i.tax_amount, i.total_amount, i.issued_at, '
                . 'i.created_by, i.created_at, i.updated_at, '
                . 'b.booking_reference, b.guest_full_name, b.guest_email, '
                . 'b.guest_phone, b.check_in, b.check_out, b.booking_status, '
                . 'p.id AS payment_id, p.transaction_reference, p.verified_at, '
                . 'pm.name AS payment_method_name, u.full_name AS created_by_name '
                . 'FROM invoices i '
                . 'INNER JOIN bookings b ON b.id = i.booking_id '
                . 'INNER JOIN payments p ON p.booking_id = i.booking_id '
                . "AND BINARY p.payment_status = 'verified' "
                . 'INNER JOIN payment_methods pm ON pm.id = p.payment_method_id '
                . 'INNER JOIN users u ON u.id = i.created_by '
                . 'WHERE i.id = :invoice_id '
                . 'ORDER BY p.id ASC',
            );
            $statement->bindValue('invoice_id', $invoiceId, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows) || count($rows) > 1) {
                throw new RuntimeException('Invoice detail could not be mapped.');
            }

            if ($rows === []) {
                return null;
            }

            $row = $rows[0];

            if (!is_array($row)) {
                throw new RuntimeException('Invoice detail could not be mapped.');
            }

            $invoice = $this->mapInvoice($row);
            $items = $this->findItems($invoice->id());

            if ($items === []) {
                throw new RuntimeException('Invoice detail has no items.');
            }

            return new AdministratorInvoiceRecord(
                $invoice,
                $items,
                $this->string($row, 'booking_reference'),
                $this->string($row, 'guest_full_name'),
                $this->string($row, 'guest_email'),
                $this->string($row, 'guest_phone'),
                $this->date($row, 'check_in'),
                $this->date($row, 'check_out'),
                $this->string($row, 'booking_status'),
                $this->positiveInteger($row, 'payment_id'),
                $this->string($row, 'payment_method_name'),
                $this->nullableString($row, 'transaction_reference'),
                $this->dateTime($row, 'verified_at'),
                $this->string($row, 'created_by_name'),
            );
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The Administrator invoice lookup failed.');
        }
    }

    public function findInvoiceByBookingId(int $bookingId): ?Invoice
    {
        if ($bookingId <= 0) {
            return null;
        }

        try {
            $sql = self::INVOICE_COLUMNS
                . ' WHERE booking_id = :booking_id LIMIT 1';

            if ($this->connection->inTransaction()) {
                $sql .= ' FOR UPDATE';
            }

            $statement = $this->connection->prepare($sql);
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('Invoice record could not be mapped.');
            }

            return $this->mapInvoice($row);
        } catch (Throwable) {
            throw new DatabaseException('The invoice lookup failed.');
        }
    }

    public function lockBooking(int $bookingId): ?Booking
    {
        if ($bookingId <= 0 || !$this->connection->inTransaction()) {
            throw new DatabaseException('The invoice booking lock failed.');
        }

        try {
            $statement = $this->connection->prepare(
                self::BOOKING_COLUMNS
                . ' WHERE id = :booking_id AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('Booking record could not be mapped.');
            }

            return $this->mapBooking($row);
        } catch (Throwable) {
            throw new DatabaseException('The invoice booking lock failed.');
        }
    }

    public function lockRoom(int $bookingId): ?BookingRoom
    {
        if ($bookingId <= 0 || !$this->connection->inTransaction()) {
            throw new DatabaseException('The invoice room lock failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'SELECT id, booking_id, room_id, price_per_night, nights, subtotal, created_at '
                . 'FROM booking_rooms WHERE booking_id = :booking_id LIMIT 1 FOR UPDATE',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('Booking room record could not be mapped.');
            }

            return new BookingRoom(
                $this->positiveInteger($row, 'id'),
                $this->positiveInteger($row, 'booking_id'),
                $this->positiveInteger($row, 'room_id'),
                $this->string($row, 'price_per_night'),
                $this->positiveInteger($row, 'nights'),
                $this->string($row, 'subtotal'),
                $this->dateTime($row, 'created_at'),
            );
        } catch (Throwable) {
            throw new DatabaseException('The invoice room lock failed.');
        }
    }

    /** @return list<Payment> */
    public function lockVerifiedPayments(int $bookingId): array
    {
        if ($bookingId <= 0 || !$this->connection->inTransaction()) {
            throw new DatabaseException('The verified payment lock failed.');
        }

        try {
            $statement = $this->connection->prepare(
                self::PAYMENT_COLUMNS
                . " WHERE booking_id = :booking_id AND BINARY payment_status = 'verified' "
                . 'ORDER BY id ASC FOR UPDATE',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('Verified payments could not be mapped.');
            }

            $payments = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('Verified payments could not be mapped.');
                }

                $payments[] = $this->mapPayment($row);
            }

            return $payments;
        } catch (Throwable) {
            throw new DatabaseException('The verified payment lock failed.');
        }
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
        if (
            !$this->connection->inTransaction()
            || $bookingId <= 0
            || $currency !== Invoice::CURRENCY_NPR
            || $taxAmount !== Invoice::ZERO_TAX
            || $issuedAt->getOffset() !== 0
            || $createdBy <= 0
            || $invoiceYear < 2000
            || $invoiceYear > 9999
        ) {
            throw new DatabaseException('The invoice write failed.');
        }

        try {
            $placeholder = 'TMP-' . bin2hex(random_bytes(16));
            $timestamp = $issuedAt->format('Y-m-d H:i:s');
            $statement = $this->connection->prepare(
                'INSERT INTO invoices ('
                . 'booking_id, invoice_number, currency, subtotal, tax_amount, '
                . 'total_amount, issued_at, created_by, created_at, updated_at'
                . ') VALUES ('
                . ':booking_id, :invoice_number, :currency, :subtotal, :tax_amount, '
                . ':total_amount, :issued_at, :created_by, :created_at, :updated_at'
                . ')',
            );
            $statement->execute([
                'booking_id' => $bookingId,
                'invoice_number' => $placeholder,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'issued_at' => $timestamp,
                'created_by' => $createdBy,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $invoiceId = filter_var(
                $this->connection->lastInsertId(),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 999999]],
            );

            if (!is_int($invoiceId)) {
                throw new RuntimeException('Invoice number sequence is exhausted.');
            }

            $invoiceNumber = sprintf(
                'TGH-INV-%04d-%06d',
                $invoiceYear,
                $invoiceId,
            );
            $update = $this->connection->prepare(
                'UPDATE invoices SET invoice_number = :invoice_number '
                . 'WHERE id = :invoice_id AND invoice_number = :placeholder',
            );
            $update->execute([
                'invoice_number' => $invoiceNumber,
                'invoice_id' => $invoiceId,
                'placeholder' => $placeholder,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Invoice number assignment failed.');
            }

            return new Invoice(
                $invoiceId,
                $bookingId,
                $invoiceNumber,
                $currency,
                $subtotal,
                $taxAmount,
                $totalAmount,
                $issuedAt,
                $createdBy,
                $issuedAt,
                $issuedAt,
            );
        } catch (Throwable) {
            throw new DatabaseException('The invoice write failed.');
        }
    }

    public function appendItem(
        int $invoiceId,
        string $description,
        string $quantity,
        string $unitPrice,
        string $lineTotal,
    ): InvoiceItem {
        if (!$this->connection->inTransaction() || $invoiceId <= 0) {
            throw new DatabaseException('The invoice item write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO invoice_items ('
                . 'invoice_id, description, quantity, unit_price, line_total'
                . ') VALUES ('
                . ':invoice_id, :description, :quantity, :unit_price, :line_total'
                . ')',
            );
            $statement->execute([
                'invoice_id' => $invoiceId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);
            $itemId = filter_var(
                $this->connection->lastInsertId(),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            if (!is_int($itemId)) {
                throw new RuntimeException('Invoice item identifier is invalid.');
            }

            return new InvoiceItem(
                $itemId,
                $invoiceId,
                $description,
                $quantity,
                $unitPrice,
                $lineTotal,
            );
        } catch (Throwable) {
            throw new DatabaseException('The invoice item write failed.');
        }
    }

    /** @return list<InvoiceItem> */
    private function findItems(int $invoiceId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, invoice_id, description, quantity, unit_price, line_total '
            . 'FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC',
        );
        $statement->bindValue('invoice_id', $invoiceId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        if (!is_array($rows)) {
            throw new RuntimeException('Invoice items could not be mapped.');
        }

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('Invoice items could not be mapped.');
            }

            $items[] = new InvoiceItem(
                $this->positiveInteger($row, 'id'),
                $this->positiveInteger($row, 'invoice_id'),
                $this->string($row, 'description'),
                $this->string($row, 'quantity'),
                $this->string($row, 'unit_price'),
                $this->string($row, 'line_total'),
            );
        }

        return $items;
    }

    /** @param array<string, mixed> $row */
    private function mapInvoice(array $row): Invoice
    {
        return new Invoice(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'booking_id'),
            $this->string($row, 'invoice_number'),
            $this->string($row, 'currency'),
            $this->string($row, 'subtotal'),
            $this->string($row, 'tax_amount'),
            $this->string($row, 'total_amount'),
            $this->dateTime($row, 'issued_at'),
            $this->positiveInteger($row, 'created_by'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapBooking(array $row): Booking
    {
        return new Booking(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'booking_reference'),
            $this->nullablePositiveInteger($row, 'user_id'),
            $this->string($row, 'guest_full_name'),
            $this->string($row, 'guest_email'),
            $this->string($row, 'guest_phone'),
            $this->date($row, 'check_in'),
            $this->date($row, 'check_out'),
            $this->positiveInteger($row, 'adults'),
            $this->nonNegativeInteger($row, 'children'),
            $this->string($row, 'currency'),
            $this->string($row, 'room_amount'),
            $this->string($row, 'total_amount'),
            $this->string($row, 'booking_status'),
            $this->nullableString($row, 'special_request'),
            $this->dateTime($row, 'terms_accepted_at'),
            $this->nullableString($row, 'policy_version'),
            $this->dateTime($row, 'confirmation_deadline_at'),
            $this->nullableDateTime($row, 'confirmed_at'),
            $this->nullableDateTime($row, 'checked_in_at'),
            $this->nullableDateTime($row, 'checked_out_at'),
            $this->nullableDateTime($row, 'cancelled_at'),
            $this->nullableString($row, 'cancellation_reason'),
            $this->nullableDateTime($row, 'rejected_at'),
            $this->nullableString($row, 'rejection_reason'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->nullableDateTime($row, 'deleted_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapPayment(array $row): Payment
    {
        return new Payment(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'booking_id'),
            $this->positiveInteger($row, 'payment_method_id'),
            $this->string($row, 'currency'),
            $this->string($row, 'amount'),
            $this->nullableString($row, 'transaction_reference'),
            $this->nullableString($row, 'proof_path'),
            $this->nullableString($row, 'guest_notes'),
            $this->string($row, 'payment_status'),
            $this->nullableDateTime($row, 'submitted_at'),
            $this->nullableDateTime($row, 'verified_at'),
            $this->nullablePositiveInteger($row, 'verified_by'),
            $this->nullableDateTime($row, 'rejected_at'),
            $this->nullablePositiveInteger($row, 'rejected_by'),
            $this->nullableString($row, 'verification_note'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function date(array $row, string $key): DateTimeImmutable
    {
        $value = $this->string($row, $key);
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (is_array($errors) && (
                $errors['warning_count'] !== 0
                || $errors['error_count'] !== 0
            ))
        ) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $date;
    }

    /** @param array<string, mixed> $row */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->positiveInteger($row, $key);
    }
}
