<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\AdministratorPaymentRecord;
use App\Models\Payment;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Reads payment attempts together with their booking and method context.
 */
final class PdoAdministratorPaymentRepository implements AdministratorPaymentCatalogRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            p.id AS payment_id,
            p.booking_id AS payment_booking_id,
            p.payment_method_id AS payment_method_id,
            p.currency AS payment_currency,
            p.amount AS payment_amount,
            p.transaction_reference AS payment_transaction_reference,
            p.proof_path AS payment_proof_path,
            p.guest_notes AS payment_guest_notes,
            p.payment_status AS payment_status,
            p.submitted_at AS payment_submitted_at,
            p.verified_at AS payment_verified_at,
            p.verified_by AS payment_verified_by,
            p.rejected_at AS payment_rejected_at,
            p.rejected_by AS payment_rejected_by,
            p.verification_note AS payment_verification_note,
            p.created_at AS payment_created_at,
            p.updated_at AS payment_updated_at,
            b.booking_reference AS booking_reference,
            b.guest_full_name AS booking_guest_full_name,
            b.guest_email AS booking_guest_email,
            b.guest_phone AS booking_guest_phone,
            b.check_in AS booking_check_in,
            b.check_out AS booking_check_out,
            b.booking_status AS booking_status,
            b.total_amount AS booking_total_amount,
            b.confirmation_deadline_at AS booking_confirmation_deadline_at,
            pm.name AS method_name,
            pm.account_reference AS method_account_reference,
            pm.requires_proof AS method_requires_proof,
            pm.is_active AS method_is_active
        FROM payments AS p
        INNER JOIN bookings AS b ON b.id = p.booking_id
        INNER JOIN payment_methods AS pm ON pm.id = p.payment_method_id
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return list<AdministratorPaymentRecord> */
    public function findAll(?string $status = null): array
    {
        if ($status !== null && !Payment::isStatus($status)) {
            return [];
        }

        try {
            $sql = self::SELECT_COLUMNS;

            if ($status !== null) {
                $sql .= ' WHERE p.payment_status = :payment_status';
            }

            $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
            $statement = $this->connection->prepare($sql);

            if ($status !== null) {
                $statement->bindValue('payment_status', $status, PDO::PARAM_STR);
            }

            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('Administrator payment records could not be mapped.');
            }

            $records = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('Administrator payment records could not be mapped.');
                }

                $records[] = $this->mapRecord($row);
            }

            return $records;
        } catch (Throwable) {
            throw new DatabaseException('The Administrator payment listing failed.');
        }
    }

    public function findById(int $id): ?AdministratorPaymentRecord
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT_COLUMNS . ' WHERE p.id = :payment_id LIMIT 1',
            );
            $statement->bindValue('payment_id', $id, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('Administrator payment record could not be mapped.');
            }

            return $this->mapRecord($row);
        } catch (Throwable) {
            throw new DatabaseException('The Administrator payment lookup failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function mapRecord(array $row): AdministratorPaymentRecord
    {
        $payment = new Payment(
            $this->positiveInteger($row, 'payment_id'),
            $this->positiveInteger($row, 'payment_booking_id'),
            $this->positiveInteger($row, 'payment_method_id'),
            $this->string($row, 'payment_currency'),
            $this->string($row, 'payment_amount'),
            $this->nullableString($row, 'payment_transaction_reference'),
            $this->nullableString($row, 'payment_proof_path'),
            $this->nullableString($row, 'payment_guest_notes'),
            $this->string($row, 'payment_status'),
            $this->nullableDateTime($row, 'payment_submitted_at'),
            $this->nullableDateTime($row, 'payment_verified_at'),
            $this->nullablePositiveInteger($row, 'payment_verified_by'),
            $this->nullableDateTime($row, 'payment_rejected_at'),
            $this->nullablePositiveInteger($row, 'payment_rejected_by'),
            $this->nullableString($row, 'payment_verification_note'),
            $this->dateTime($row, 'payment_created_at'),
            $this->dateTime($row, 'payment_updated_at'),
        );

        return new AdministratorPaymentRecord(
            $payment,
            $this->string($row, 'booking_reference'),
            $this->string($row, 'booking_guest_full_name'),
            $this->string($row, 'booking_guest_email'),
            $this->string($row, 'booking_guest_phone'),
            $this->date($row, 'booking_check_in'),
            $this->date($row, 'booking_check_out'),
            $this->string($row, 'booking_status'),
            $this->string($row, 'booking_total_amount'),
            $this->dateTime($row, 'booking_confirmation_deadline_at'),
            $this->string($row, 'method_name'),
            $this->nullableString($row, 'method_account_reference'),
            $this->boolean($row, 'method_requires_proof'),
            $this->boolean($row, 'method_is_active'),
        );
    }

    /** @param array<string, mixed> $row */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->positiveInteger($row, $key);
    }

    /** @param array<string, mixed> $row */
    private function date(array $row, string $key): DateTimeImmutable
    {
        $value = $this->string($row, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
        ) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $date;
    }
}
