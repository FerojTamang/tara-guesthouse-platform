<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Payment;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides strict read access to retained payment attempts.
 */
final class PaymentRepository implements PaymentCatalogRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
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

    /**
     * @return list<Payment>
     */
    public function findAll(?string $status = null): array
    {
        if ($status !== null && !Payment::isStatus($status)) {
            return [];
        }

        try {
            $sql = self::SELECT_COLUMNS;

            if ($status !== null) {
                $sql .= ' WHERE BINARY payment_status = BINARY :payment_status';
            }

            $sql .= ' ORDER BY created_at DESC, id DESC';
            $statement = $this->connection->prepare($sql);

            if ($status !== null) {
                $statement->bindValue('payment_status', $status, PDO::PARAM_STR);
            }

            $statement->execute();

            return $this->mapListing($statement->fetchAll());
        } catch (Throwable) {
            throw new DatabaseException('The payment listing failed.');
        }
    }

    public function findById(int $id): ?Payment
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE id = :lookup LIMIT 1',
            $id,
            'The payment lookup failed.',
        );
    }

    /**
     * @return list<Payment>
     */
    public function findAllByBookingId(int $bookingId): array
    {
        if ($bookingId <= 0) {
            return [];
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT_COLUMNS
                . ' WHERE booking_id = :booking_id ORDER BY created_at DESC, id DESC',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();

            return $this->mapListing($statement->fetchAll());
        } catch (Throwable) {
            throw new DatabaseException('The booking payment listing failed.');
        }
    }

    public function findVerifiedByBookingId(int $bookingId): ?Payment
    {
        if ($bookingId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT_COLUMNS
                . ' WHERE booking_id = :booking_id '
                . "AND BINARY payment_status = BINARY 'verified' "
                . 'ORDER BY verified_at ASC, id ASC LIMIT 1',
            );
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The verified payment record could not be mapped.');
            }

            return $this->mapPayment($row);
        } catch (Throwable) {
            throw new DatabaseException('The verified payment lookup failed.');
        }
    }

    private function lookup(string $sql, int $value, string $failureMessage): ?Payment
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The payment record could not be mapped.');
            }

            return $this->mapPayment($row);
        } catch (Throwable) {
            throw new DatabaseException($failureMessage);
        }
    }

    /**
     * @param mixed $rows
     * @return list<Payment>
     */
    private function mapListing(mixed $rows): array
    {
        if (!is_array($rows)) {
            throw new RuntimeException('The payment records could not be mapped.');
        }

        $payments = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('The payment records could not be mapped.');
            }

            $payments[] = $this->mapPayment($row);
        }

        return $payments;
    }

    /**
     * @param array<string, mixed> $row
     */
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

    /**
     * @param array<string, mixed> $row
     */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->positiveInteger($row, $key);
    }
}
