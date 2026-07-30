<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorPaymentException;
use App\Exceptions\BookingException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentStatusTransitionResult;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PaymentWriteRepository;
use App\Requests\AdministratorPaymentStatusRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Reviews one submitted payment attempt and records the decision atomically.
 */
final readonly class AdministratorPaymentStatusService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private PaymentWriteRepository $payments,
        private AuditLogWriteRepository $auditLogs,
        private VerifiedPaymentBookingConfirmer $bookingConfirmer,
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

    public function transition(
        int $paymentId,
        AdministratorPaymentStatusRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): PaymentStatusTransitionResult {
        if ($paymentId <= 0 || $administratorId <= 0) {
            throw AdministratorPaymentException::validation(
                'Payment review request is invalid.',
            );
        }

        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $current = $this->payments->lockCurrent($paymentId);

            if (!$current instanceof Payment) {
                throw AdministratorPaymentException::paymentNotFound();
            }

            if (!$current->isSubmitted()) {
                throw AdministratorPaymentException::transitionConflict();
            }

            $targetStatus = $request->targetStatus();
            [$reviewed, $changedAt] = match ($targetStatus) {
                Payment::STATUS_VERIFIED => $this->verify(
                    $current,
                    $administratorId,
                    $request->verificationNote(),
                    $ipAddress,
                    $userAgent,
                ),
                Payment::STATUS_REJECTED => $this->reject(
                    $current,
                    $administratorId,
                    $request->verificationNote(),
                ),
                default => throw new RuntimeException(
                    'The payment target status is invalid.',
                ),
            };

            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                $this->auditEventType($targetStatus),
                'payment',
                $current->id(),
                $this->auditDescription($targetStatus),
                $this->auditMetadata(
                    $current->bookingId(),
                    $current->paymentStatus(),
                    $targetStatus,
                ),
                $ipAddress,
                $userAgent,
                $changedAt,
            ));

            $commitAttempted = true;
            $this->transaction->commit();

            return new PaymentStatusTransitionResult(
                $reviewed->id(),
                $reviewed->bookingId(),
                $current->paymentStatus(),
                $reviewed->paymentStatus(),
                $changedAt,
            );
        } catch (Throwable $failure) {
            $safeFailure = $this->safeFailure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw AdministratorPaymentException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw AdministratorPaymentException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw AdministratorPaymentException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    /** @return array{Payment, DateTimeImmutable} */
    private function verify(
        Payment $current,
        int $administratorId,
        string $note,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $booking = $this->payments->lockBookingById($current->bookingId());
        $changedAt = $this->now();

        if (
            !$booking instanceof Booking
            || $booking->bookingStatus() !== Booking::STATUS_PENDING
            || $changedAt >= $booking->confirmationDeadlineAt()
            || $current->currency() !== $booking->currency()
            || $current->amount() !== $booking->totalAmount()
            || $this->payments->hasVerifiedForBooking(
                $booking->id(),
                $current->id(),
            )
        ) {
            throw AdministratorPaymentException::transitionConflict();
        }

        $reviewed = $this->payments->markVerified(
            $current,
            $administratorId,
            $note,
            $changedAt,
        );
        $this->bookingConfirmer->confirm(
            $booking,
            $reviewed,
            $administratorId,
            $ipAddress,
            $userAgent,
            $changedAt,
        );

        return [$reviewed, $changedAt];
    }

    /** @return array{Payment, DateTimeImmutable} */
    private function reject(
        Payment $current,
        int $administratorId,
        string $note,
    ): array {
        $changedAt = $this->now();

        return [
            $this->payments->markRejected(
                $current,
                $administratorId,
                $note,
                $changedAt,
            ),
            $changedAt,
        ];
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw AdministratorPaymentException::failure();
        }

        return $value;
    }

    private function auditEventType(string $targetStatus): string
    {
        return match ($targetStatus) {
            Payment::STATUS_VERIFIED => 'payment_verified',
            Payment::STATUS_REJECTED => 'payment_rejected',
            default => throw new RuntimeException(
                'The payment audit event type is invalid.',
            ),
        };
    }

    private function auditDescription(string $targetStatus): string
    {
        return match ($targetStatus) {
            Payment::STATUS_VERIFIED => 'Administrator verified a submitted payment.',
            Payment::STATUS_REJECTED => 'Administrator rejected a submitted payment.',
            default => throw new RuntimeException(
                'The payment audit description is invalid.',
            ),
        };
    }

    private function auditMetadata(
        int $bookingId,
        string $fromStatus,
        string $toStatus,
    ): string {
        try {
            return json_encode(
                [
                    'booking_id' => $bookingId,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException(
                'The payment audit metadata could not be encoded.',
                previous: $failure,
            );
        }
    }

    private function safeFailure(Throwable $failure): AdministratorPaymentException
    {
        if ($failure instanceof AdministratorPaymentException) {
            return $failure;
        }

        if ($failure instanceof BookingException && $failure->isConflict()) {
            return AdministratorPaymentException::transitionConflict();
        }

        return AdministratorPaymentException::failure($failure);
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
