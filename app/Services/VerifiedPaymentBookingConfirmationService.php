<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusSnapshot;
use App\Models\BookingStatusTransitionResult;
use App\Models\Payment;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingStatusWriteRepository;
use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Confirms one locked pending booking from one qualifying verified payment.
 *
 * The caller owns the surrounding transaction. This service deliberately has
 * no public/controller entry point, so confirmed cannot become an arbitrary
 * Administrator booking-state choice.
 */
final readonly class VerifiedPaymentBookingConfirmationService implements VerifiedPaymentBookingConfirmer
{
    public function __construct(
        private BookingStatusWriteRepository $bookings,
        private AuditLogWriteRepository $auditLogs,
    ) {
    }

    public function confirm(
        Booking $booking,
        Payment $verifiedPayment,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $confirmedAt,
    ): BookingStatusTransitionResult {
        $this->assertEligible(
            $booking,
            $verifiedPayment,
            $administratorId,
            $confirmedAt,
        );

        try {
            $current = new BookingStatusSnapshot(
                $booking->id(),
                $booking->bookingReference(),
                $booking->bookingStatus(),
            );
            $eventMetadata = $this->eventMetadata($verifiedPayment->id());

            $this->bookings->updateStatus(
                $current,
                Booking::STATUS_CONFIRMED,
                null,
                $confirmedAt,
            );
            $this->bookings->appendEvent(
                $booking->id(),
                'booking_confirmed',
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                $administratorId,
                null,
                $confirmedAt,
                $eventMetadata,
            );
            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                'booking_confirmed_from_payment',
                'booking',
                $booking->id(),
                'Booking confirmed after Administrator verified payment.',
                $this->auditMetadata($verifiedPayment->id()),
                $ipAddress,
                $userAgent,
                $confirmedAt,
            ));

            return new BookingStatusTransitionResult(
                $booking->id(),
                $booking->bookingReference(),
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                $confirmedAt,
            );
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    private function assertEligible(
        Booking $booking,
        Payment $verifiedPayment,
        int $administratorId,
        DateTimeImmutable $confirmedAt,
    ): void {
        if ($administratorId <= 0 || $confirmedAt->getOffset() !== 0) {
            throw BookingException::failure();
        }

        if (
            $booking->bookingStatus() !== Booking::STATUS_PENDING
            || $booking->confirmedAt() !== null
            || $confirmedAt >= $booking->confirmationDeadlineAt()
            || !$verifiedPayment->isVerified()
            || $verifiedPayment->bookingId() !== $booking->id()
            || $verifiedPayment->currency() !== Booking::CURRENCY_NPR
            || $booking->currency() !== Booking::CURRENCY_NPR
            || $verifiedPayment->amount() !== $booking->totalAmount()
            || $verifiedPayment->verifiedBy() !== $administratorId
            || $verifiedPayment->verifiedAt() === null
            || $verifiedPayment->verifiedAt()->format('Y-m-d H:i:s')
                !== $confirmedAt->format('Y-m-d H:i:s')
        ) {
            throw BookingException::transitionConflict();
        }
    }

    private function eventMetadata(int $paymentId): string
    {
        return $this->encodeMetadata(['payment_id' => $paymentId]);
    }

    private function auditMetadata(int $paymentId): string
    {
        return $this->encodeMetadata([
            'payment_id' => $paymentId,
            'from_status' => Booking::STATUS_PENDING,
            'to_status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    /** @param array<string, int|string> $metadata */
    private function encodeMetadata(array $metadata): string
    {
        try {
            return json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException(
                'The booking confirmation metadata could not be encoded.',
                previous: $failure,
            );
        }
    }
}
