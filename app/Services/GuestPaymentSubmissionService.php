<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\GuestPaymentAccess;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\PaymentWriteRepository;
use App\Requests\GuestPaymentRequest;
use App\Storage\PaymentProofStorage;
use App\Storage\StoredPaymentProof;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Creates one retained submitted payment attempt in a coordinated transaction.
 */
final class GuestPaymentSubmissionService implements GuestPaymentSubmitter
{
    /** @var Closure(): mixed */
    private readonly Closure $clock;

    /** @param null|callable(): mixed $clock */
    public function __construct(
        private readonly GuestPaymentAccessValidator $accessValidator,
        private readonly BookingAccessToken $tokens,
        private readonly PaymentWriteRepository $payments,
        private readonly PaymentProofStorage $proofs,
        private readonly BookingTransaction $transaction,
        ?callable $clock = null,
        private readonly ?TransactionalMailDispatcher $mail = null,
        private readonly ?AdministratorEventNotifier $notifications = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            )
            : Closure::fromCallable($clock);
    }

    public function submit(GuestPaymentRequest $request): Payment
    {
        $this->rejectExistingTransaction();
        $access = $this->accessValidator->validate($request);
        $storedProof = null;
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $booking = $this->payments->lockBookingByReference(
                $request->bookingReference(),
            );

            if (!$booking instanceof Booking) {
                throw GuestPaymentException::accessDenied();
            }

            $paymentMethod = $this->payments->lockPaymentMethodById(
                $request->paymentMethodId(),
            );

            if (!$paymentMethod instanceof PaymentMethod) {
                throw GuestPaymentException::paymentMethodUnavailable();
            }

            $submittedAt = $this->currentTime();
            $this->validateLockedFacts(
                $request,
                $access,
                $booking,
                $paymentMethod,
                $submittedAt,
            );

            if ($this->payments->hasBlockingSubmissionForBooking($booking->id())) {
                throw GuestPaymentException::paymentAlreadySubmitted();
            }

            $proofPath = null;
            $proof = $request->proof();

            if ($proof !== null) {
                $storedProof = $this->proofs->store(
                    $proof->file(),
                    $proof->detectedMediaType(),
                );
                $proofPath = $storedProof->relativePath();
            }

            $payment = $this->payments->createSubmitted(
                $booking->id(),
                $paymentMethod->id(),
                $request->amount(),
                $request->transactionReference(),
                $proofPath,
                $request->guestNotes(),
                $submittedAt,
            );

            $commitAttempted = true;
            $this->transaction->commit();
            $this->deliverAdministratorNotification($booking, $payment);
            $this->deliverPaymentSubmitted($booking, $payment);

            return $payment;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof GuestPaymentException
                ? $failure
                : GuestPaymentException::failure($failure);
            $rolledBack = false;
            $rollbackFailure = null;

            try {
                if ($this->transaction->inProgress()) {
                    $this->transaction->rollBack();
                    $rolledBack = true;
                }
            } catch (Throwable $transactionFailure) {
                $rollbackFailure = $transactionFailure;
            }

            if (
                $storedProof instanceof StoredPaymentProof
                && (!$commitAttempted || $rolledBack)
            ) {
                $this->removeProof($storedProof);
            }

            if ($rollbackFailure !== null) {
                throw GuestPaymentException::failure($rollbackFailure);
            }

            if ($commitAttempted) {
                throw GuestPaymentException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }


    private function deliverAdministratorNotification(Booking $booking, Payment $payment): void
    {
        if (!$this->notifications instanceof AdministratorEventNotifier) {
            return;
        }

        try {
            $this->notifications->paymentSubmitted($booking, $payment);
        } catch (Throwable) {
            error_log('Payment post-commit Administrator notification delivery failed.');
        }
    }

    private function deliverPaymentSubmitted(Booking $booking, Payment $payment): void
    {
        if (!$this->mail instanceof TransactionalMailDispatcher) {
            return;
        }

        try {
            $this->mail->paymentSubmitted($booking, $payment);
        } catch (Throwable) {
            error_log('Payment post-commit email delivery failed.');
        }
    }

    private function validateLockedFacts(
        GuestPaymentRequest $request,
        GuestPaymentAccess $access,
        Booking $booking,
        PaymentMethod $paymentMethod,
        DateTimeImmutable $submittedAt,
    ): void {
        if (
            $booking->id() !== $access->booking()->id()
            || $booking->bookingReference() !== $request->bookingReference()
            || !$this->tokens->validates(
                $request->bookingAccessToken(),
                $booking->bookingReference(),
                $booking->confirmationDeadlineAt(),
                $submittedAt,
            )
        ) {
            throw GuestPaymentException::accessDenied();
        }

        if (
            $booking->bookingStatus() !== Booking::STATUS_PENDING
            || $submittedAt >= $booking->confirmationDeadlineAt()
        ) {
            throw GuestPaymentException::bookingConflict();
        }

        if (
            $paymentMethod->id() !== $access->paymentMethod()->id()
            || !$paymentMethod->isActive()
        ) {
            throw GuestPaymentException::paymentMethodUnavailable();
        }

        if (
            $request->currency() !== Booking::CURRENCY_NPR
            || $booking->currency() !== Booking::CURRENCY_NPR
            || $request->amount() !== $booking->totalAmount()
        ) {
            throw GuestPaymentException::validation(
                'Payment amount must match the booking total.',
            );
        }

        if ($paymentMethod->requiresProof() && !$request->hasProof()) {
            throw GuestPaymentException::validation('Payment proof is required.');
        }
    }

    private function currentTime(): DateTimeImmutable
    {
        $current = ($this->clock)();

        if (!$current instanceof DateTimeImmutable || $current->getOffset() !== 0) {
            throw new RuntimeException('The payment submission clock is invalid.');
        }

        return $current;
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw GuestPaymentException::failure();
            }
        } catch (GuestPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }

    private function removeProof(StoredPaymentProof $proof): void
    {
        try {
            $this->proofs->remove($proof->relativePath());
        } catch (Throwable $cleanupFailure) {
            throw GuestPaymentException::failure($cleanupFailure);
        }
    }
}
