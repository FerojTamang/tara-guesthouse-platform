<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorPaymentException;
use App\Models\AdministratorPaymentRecord;
use App\Models\AuditLogEntry;
use App\Models\Payment;
use App\Repositories\AdministratorPaymentCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Storage\PaymentProofReader;
use App\Storage\RetrievedPaymentProof;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Provides read-only Administrator payment review and protected proof access.
 */
final readonly class AdministratorPaymentService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private AdministratorPaymentCatalogRepository $payments,
        private PaymentProofReader $proofs,
        private AuditLogWriteRepository $auditLogs,
        private BookingTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ),
        );
    }

    /** @return list<AdministratorPaymentRecord> */
    public function list(?string $status = null): array
    {
        try {
            if ($status !== null && !Payment::isStatus($status)) {
                throw AdministratorPaymentException::validation(
                    'Payment status filter is invalid.',
                );
            }

            return $this->payments->findAll($status);
        } catch (AdministratorPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AdministratorPaymentException::failure($failure);
        }
    }

    public function detail(int $paymentId): ?AdministratorPaymentRecord
    {
        if ($paymentId <= 0) {
            return null;
        }

        try {
            return $this->payments->findById($paymentId);
        } catch (Throwable $failure) {
            throw AdministratorPaymentException::failure($failure);
        }
    }

    public function proof(
        int $paymentId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?RetrievedPaymentProof {
        if ($paymentId <= 0) {
            return null;
        }

        if ($administratorId <= 0) {
            throw AdministratorPaymentException::failure();
        }

        $this->rejectExistingTransaction();

        try {
            $record = $this->payments->findById($paymentId);

            if (!$record instanceof AdministratorPaymentRecord) {
                return null;
            }

            $path = $record->payment()->proofPath();

            if ($path === null) {
                return null;
            }

            $proof = $this->proofs->read($path);

            if (!$proof instanceof RetrievedPaymentProof) {
                return null;
            }

            $accessedAt = ($this->clock)();

            if (
                !$accessedAt instanceof DateTimeImmutable
                || $accessedAt->getOffset() !== 0
            ) {
                throw new RuntimeException('Payment proof audit clock is invalid.');
            }

            $this->transaction->begin();
            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                'payment_proof_accessed',
                'payment',
                $paymentId,
                'Administrator accessed a payment proof.',
                null,
                $ipAddress,
                $userAgent,
                $accessedAt,
            ));
            $this->transaction->commit();

            return $proof;
        } catch (Throwable $failure) {
            try {
                if ($this->transaction->inProgress()) {
                    $this->transaction->rollBack();
                }
            } catch (Throwable $rollbackFailure) {
                throw AdministratorPaymentException::failure($rollbackFailure);
            }

            throw AdministratorPaymentException::failure($failure);
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw AdministratorPaymentException::failure();
            }
        } catch (AdministratorPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AdministratorPaymentException::failure($failure);
        }
    }
}
