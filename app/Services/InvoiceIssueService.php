<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorInvoiceException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceIssueResult;
use App\Models\Payment;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\InvoiceWriteRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Issues one stable immutable invoice inside a single transaction.
 */
final readonly class InvoiceIssueService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private InvoiceWriteRepository $invoices,
        private AuditLogWriteRepository $auditLogs,
        private BookingTransaction $transaction,
        private DateTimeZone $businessTimezone,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ),
        );
    }

    public function issue(
        int $bookingId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): InvoiceIssueResult {
        if ($bookingId <= 0 || $administratorId <= 0) {
            throw AdministratorInvoiceException::validation(
                'Invoice issue request is invalid.',
            );
        }

        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $booking = $this->invoices->lockBooking($bookingId);

            if (!$booking instanceof Booking) {
                throw AdministratorInvoiceException::notFound();
            }

            $existing = $this->invoices->findInvoiceByBookingId($bookingId);

            if ($existing instanceof Invoice) {
                $commitAttempted = true;
                $this->transaction->commit();

                return new InvoiceIssueResult($existing, false);
            }

            $room = $this->invoices->lockRoom($bookingId);
            $verifiedPayments = $this->invoices->lockVerifiedPayments($bookingId);
            $issuedAt = $this->now();

            if ($room === null || count($verifiedPayments) !== 1) {
                throw AdministratorInvoiceException::conflict();
            }

            $payment = $verifiedPayments[0];

            if (
                !$payment instanceof Payment
                || $booking->bookingStatus() !== Booking::STATUS_CONFIRMED
                || $booking->confirmedAt() === null
                || !$payment->isVerified()
                || $payment->bookingId() !== $booking->id()
                || $payment->verifiedAt() === null
                || $payment->verifiedAt() > $issuedAt
                || $booking->confirmedAt() > $issuedAt
                || $booking->currency() !== Invoice::CURRENCY_NPR
                || $payment->currency() !== Invoice::CURRENCY_NPR
                || $payment->amount() !== $booking->totalAmount()
                || $room->bookingId() !== $booking->id()
                || $room->subtotal() !== $booking->roomAmount()
                || $booking->roomAmount() !== $booking->totalAmount()
                || !$this->roomSnapshotBalances(
                    $room->pricePerNight(),
                    $room->nights(),
                    $room->subtotal(),
                )
            ) {
                throw AdministratorInvoiceException::conflict();
            }

            $invoice = $this->invoices->createInvoice(
                $booking->id(),
                Invoice::CURRENCY_NPR,
                $booking->totalAmount(),
                Invoice::ZERO_TAX,
                $booking->totalAmount(),
                $issuedAt,
                $administratorId,
                (int) $issuedAt
                    ->setTimezone($this->businessTimezone)
                    ->format('Y'),
            );
            $this->invoices->appendItem(
                $invoice->id(),
                'Accommodation',
                sprintf('%d.00', $room->nights()),
                $room->pricePerNight(),
                $room->subtotal(),
            );
            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                'invoice_issued',
                'invoice',
                $invoice->id(),
                'Administrator issued an invoice from verified booking snapshots.',
                $this->auditMetadata(
                    $booking->id(),
                    $payment->id(),
                    $invoice->invoiceNumber(),
                ),
                $ipAddress,
                $userAgent,
                $issuedAt,
            ));

            $commitAttempted = true;
            $this->transaction->commit();

            return new InvoiceIssueResult($invoice, true);
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof AdministratorInvoiceException
                ? $failure
                : AdministratorInvoiceException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw AdministratorInvoiceException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw AdministratorInvoiceException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw AdministratorInvoiceException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw AdministratorInvoiceException::failure();
        }

        return $value;
    }

    private function roomSnapshotBalances(
        string $pricePerNight,
        int $nights,
        string $subtotal,
    ): bool {
        $price = $this->moneyToCents($pricePerNight);
        $total = $this->moneyToCents($subtotal);

        return $price !== null
            && $total !== null
            && $nights >= 1
            && $price * $nights === $total;
    }

    private function moneyToCents(string $money): ?int
    {
        if (preg_match('/^(?:0|[1-9][0-9]{0,9})\.([0-9]{2})$/D', $money, $matches) !== 1) {
            return null;
        }

        [$whole] = explode('.', $money, 2);
        $wholeValue = (int) $whole;
        $fraction = (int) $matches[1];

        return ($wholeValue * 100) + $fraction;
    }

    private function auditMetadata(
        int $bookingId,
        int $paymentId,
        string $invoiceNumber,
    ): string {
        try {
            return json_encode(
                [
                    'booking_id' => $bookingId,
                    'payment_id' => $paymentId,
                    'invoice_number' => $invoiceNumber,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException(
                'Invoice audit metadata could not be encoded.',
                previous: $failure,
            );
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw AdministratorInvoiceException::failure();
            }
        } catch (AdministratorInvoiceException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AdministratorInvoiceException::failure($failure);
        }
    }
}
