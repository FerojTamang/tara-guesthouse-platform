<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Payment;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Locks and mutates payment attempts inside caller-owned transactions.
 */
final readonly class PdoPaymentWriteRepository implements PaymentWriteRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function lockCurrent(int $paymentId): ?Payment
    {
        if ($paymentId <= 0 || !$this->connection->inTransaction()) {
            throw new DatabaseException('The payment lock failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'SELECT id FROM payments WHERE id = :payment_id LIMIT 1 FOR UPDATE',
            );
            $statement->bindValue('payment_id', $paymentId, PDO::PARAM_INT);
            $statement->execute();

            if ($statement->fetch() === false) {
                return null;
            }

            $payment = (new PaymentRepository($this->connection))->findById($paymentId);

            if (!$payment instanceof Payment) {
                throw new RuntimeException('The locked payment could not be loaded.');
            }

            return $payment;
        } catch (Throwable) {
            throw new DatabaseException('The payment lock failed.');
        }
    }

    public function createSubmitted(
        int $bookingId,
        int $paymentMethodId,
        string $amount,
        ?string $transactionReference,
        ?string $proofPath,
        ?string $guestNotes,
        DateTimeImmutable $submittedAt,
    ): Payment {
        if (
            !$this->connection->inTransaction()
            || $bookingId <= 0
            || $paymentMethodId <= 0
            || !$this->isPositiveMoney($amount)
            || ($transactionReference !== null && strlen($transactionReference) > 100)
            || ($proofPath !== null && !$this->isSafeRelativePath($proofPath))
            || $submittedAt->getOffset() !== 0
        ) {
            throw new DatabaseException('The payment write failed.');
        }

        try {
            $timestamp = $submittedAt->format('Y-m-d H:i:s');
            $statement = $this->connection->prepare(
                'INSERT INTO payments ('
                . 'booking_id, payment_method_id, currency, amount, '
                . 'transaction_reference, proof_path, guest_notes, payment_status, '
                . 'submitted_at, verified_at, verified_by, rejected_at, rejected_by, '
                . 'verification_note, created_at, updated_at'
                . ') VALUES ('
                . ':booking_id, :payment_method_id, :currency, :amount, '
                . ':transaction_reference, :proof_path, :guest_notes, :payment_status, '
                . ':submitted_at, NULL, NULL, NULL, NULL, NULL, :created_at, :updated_at'
                . ')',
            );
            $statement->execute([
                'booking_id' => $bookingId,
                'payment_method_id' => $paymentMethodId,
                'currency' => Payment::CURRENCY_NPR,
                'amount' => $amount,
                'transaction_reference' => $transactionReference,
                'proof_path' => $proofPath,
                'guest_notes' => $guestNotes,
                'payment_status' => Payment::STATUS_SUBMITTED,
                'submitted_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $payment = (new PaymentRepository($this->connection))->findById(
                $this->generatedIdentifier(),
            );

            if (!$payment instanceof Payment) {
                throw new RuntimeException('The created payment could not be loaded.');
            }

            return $payment;
        } catch (Throwable) {
            throw new DatabaseException('The payment write failed.');
        }
    }

    public function hasVerifiedForBooking(
        int $bookingId,
        ?int $excludingPaymentId = null,
    ): bool {
        if (
            !$this->connection->inTransaction()
            || $bookingId <= 0
            || ($excludingPaymentId !== null && $excludingPaymentId <= 0)
        ) {
            throw new DatabaseException('The verified payment lookup failed.');
        }

        try {
            $sql = 'SELECT 1 FROM payments '
                . 'WHERE booking_id = :booking_id '
                . "AND BINARY payment_status = BINARY 'verified'";

            if ($excludingPaymentId !== null) {
                $sql .= ' AND id <> :excluding_payment_id';
            }

            $sql .= ' LIMIT 1';
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('booking_id', $bookingId, PDO::PARAM_INT);

            if ($excludingPaymentId !== null) {
                $statement->bindValue(
                    'excluding_payment_id',
                    $excludingPaymentId,
                    PDO::PARAM_INT,
                );
            }

            $statement->execute();

            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            throw new DatabaseException('The verified payment lookup failed.');
        }
    }

    public function markVerified(
        Payment $current,
        int $verifiedBy,
        string $note,
        DateTimeImmutable $verifiedAt,
    ): Payment {
        return $this->review(
            $current,
            Payment::STATUS_VERIFIED,
            $verifiedBy,
            $note,
            $verifiedAt,
        );
    }

    public function markRejected(
        Payment $current,
        int $rejectedBy,
        string $note,
        DateTimeImmutable $rejectedAt,
    ): Payment {
        return $this->review(
            $current,
            Payment::STATUS_REJECTED,
            $rejectedBy,
            $note,
            $rejectedAt,
        );
    }

    private function review(
        Payment $current,
        string $targetStatus,
        int $actorId,
        string $note,
        DateTimeImmutable $reviewedAt,
    ): Payment {
        if (
            !$this->connection->inTransaction()
            || !$current->isSubmitted()
            || !in_array(
                $targetStatus,
                [Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED],
                true,
            )
            || $actorId <= 0
            || $note === ''
            || $note !== trim($note)
            || $reviewedAt->getOffset() !== 0
        ) {
            throw new DatabaseException('The payment status update failed.');
        }

        try {
            $timestamp = $reviewedAt->format('Y-m-d H:i:s');

            if ($targetStatus === Payment::STATUS_VERIFIED) {
                $assignment = 'verified_at = :reviewed_at, verified_by = :actor_id, '
                    . 'rejected_at = NULL, rejected_by = NULL';
            } else {
                $assignment = 'rejected_at = :reviewed_at, rejected_by = :actor_id, '
                    . 'verified_at = NULL, verified_by = NULL';
            }

            $statement = $this->connection->prepare(
                'UPDATE payments SET '
                . 'payment_status = :target_status, '
                . $assignment
                . ', verification_note = :verification_note, updated_at = :updated_at '
                . 'WHERE id = :payment_id '
                . 'AND BINARY payment_status = BINARY :current_status',
            );
            $statement->execute([
                'target_status' => $targetStatus,
                'reviewed_at' => $timestamp,
                'actor_id' => $actorId,
                'verification_note' => $note,
                'updated_at' => $timestamp,
                'payment_id' => $current->id(),
                'current_status' => Payment::STATUS_SUBMITTED,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The payment status changed concurrently.');
            }

            $payment = (new PaymentRepository($this->connection))->findById($current->id());

            if (!$payment instanceof Payment) {
                throw new RuntimeException('The reviewed payment could not be loaded.');
            }

            return $payment;
        } catch (Throwable) {
            throw new DatabaseException('The payment status update failed.');
        }
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated payment identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated payment identifier is invalid.');
        }

        return $integer;
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }

    private function isSafeRelativePath(string $path): bool
    {
        if (
            $path === ''
            || !mb_check_encoding($path, 'UTF-8')
            || mb_strlen($path, 'UTF-8') > 255
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_contains($path, '\\')
            || str_contains($path, '//')
            || str_contains($path, "\0")
        ) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
