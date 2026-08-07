<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotificationException;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\Review;
use App\Repositories\AdministratorNotificationRecipientRepository;
use Throwable;

/** Fans approved Guest-originated events out to every active Administrator inbox. */
final readonly class AdministratorEventNotificationService implements AdministratorEventNotifier
{
    public function __construct(
        private AdministratorNotificationRecipientRepository $recipients,
        private AdministratorNotificationService $notifications,
    ) {
    }

    public function bookingReceived(Booking $booking): void
    {
        $this->dispatch(
            'booking_received',
            'New booking received',
            sprintf(
                'Booking %s was submitted by %s for %s to %s.',
                $booking->bookingReference(),
                $booking->guestFullName(),
                $booking->checkIn()->format('Y-m-d'),
                $booking->checkOut()->format('Y-m-d'),
            ),
            'booking',
            $booking->id(),
        );
    }

    public function contactMessageReceived(ContactMessage $contact): void
    {
        $this->dispatch(
            'contact_message_received',
            'New contact message',
            sprintf('Contact message from %s: %s', $contact->name(), $contact->subject()),
            'contact_message',
            $contact->id(),
        );
    }


    public function paymentSubmitted(Booking $booking, Payment $payment): void
    {
        $this->dispatch(
            'payment_submitted',
            'Payment awaiting verification',
            sprintf(
                'Payment #%d for booking %s was submitted for %s %s.',
                $payment->id(),
                $booking->bookingReference(),
                $payment->currency(),
                $payment->amount(),
            ),
            'payment',
            $payment->id(),
        );
    }

    public function reviewSubmitted(Review $review): void
    {
        $this->dispatch(
            'review_submitted',
            'Review awaiting moderation',
            sprintf(
                'A %d-star verified-stay review was submitted by %s.',
                $review->rating(),
                $review->guestNameSnapshot(),
            ),
            'review',
            $review->id(),
        );
    }

    private function dispatch(
        string $type,
        string $title,
        string $message,
        string $relatedType,
        int $relatedId,
    ): void {
        $failed = false;

        foreach ($this->recipients->findActiveAdministratorIds() as $userId) {
            try {
                $this->notifications->create(
                    $userId,
                    $type,
                    $title,
                    $message,
                    $relatedType,
                    $relatedId,
                );
            } catch (Throwable) {
                $failed = true;
            }
        }

        if ($failed) {
            throw NotificationException::failure();
        }
    }
}
