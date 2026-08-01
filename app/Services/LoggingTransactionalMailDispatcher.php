<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Models\Payment;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Throwable;

/** Sends approved mail events and logs only non-sensitive failure metadata. */
final readonly class LoggingTransactionalMailDispatcher implements TransactionalMailDispatcher
{
    public function __construct(
        private TransactionalMailServiceProvider $services,
        private LoggerInterface $logger,
    ) {
    }

    public function bookingReceived(Booking $booking): void
    {
        $this->dispatch(
            'booking_received',
            ['booking_id' => $booking->id()],
            fn () => $this->services->service()->sendBookingReceived($booking),
        );
    }

    public function bookingStatusChanged(Booking $booking, string $status): void
    {
        $event = match ($status) {
            Booking::STATUS_CANCELLED => 'booking_cancelled',
            Booking::STATUS_REJECTED => 'booking_rejected',
            default => 'booking_status_invalid',
        };

        $this->dispatch(
            $event,
            ['booking_id' => $booking->id()],
            fn () => $this->services->service()->sendBookingStatusChanged($booking, $status),
        );
    }

    public function paymentSubmitted(Booking $booking, Payment $payment): void
    {
        $this->dispatch(
            'payment_submitted',
            ['booking_id' => $booking->id(), 'payment_id' => $payment->id()],
            fn () => $this->services->service()->sendPaymentSubmitted($booking, $payment),
        );
    }

    public function paymentReviewed(Booking $booking, Payment $payment): void
    {
        $event = match (true) {
            $payment->isVerified() => 'payment_verified',
            $payment->isRejected() => 'payment_rejected',
            default => 'payment_status_invalid',
        };

        $this->dispatch(
            $event,
            [
                'booking_id' => $booking->id(),
                'payment_id' => $payment->id(),
            ],
            fn () => $this->services->service()->sendPaymentReviewed($booking, $payment),
        );
    }

    public function reviewInvitation(
        Booking $booking,
        #[SensitiveParameter] IssuedReviewInvitation $invitation,
    ): void {
        $this->dispatch(
            'review_invitation',
            [
                'booking_id' => $booking->id(),
                'review_invitation_id' => $invitation->invitation()->id(),
            ],
            fn () => $this->services->service()->sendReviewInvitation($booking, $invitation),
        );
    }

    /**
     * @param array<string, int> $context
     * @param callable(): void $operation
     */
    private function dispatch(string $event, array $context, callable $operation): void
    {
        try {
            $operation();
        } catch (Throwable) {
            try {
                $this->logger->warning('transactional_mail_delivery_failed', [
                    'mail_event' => $event,
                    ...$context,
                ]);
            } catch (Throwable) {
                error_log('Transactional mail delivery logging failed.');
            }
        }
    }
}
