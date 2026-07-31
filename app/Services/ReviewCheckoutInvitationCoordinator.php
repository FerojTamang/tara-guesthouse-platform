<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IssuedReviewInvitation;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Throwable;

/** Creates invitations inside checkout and delivers raw tokens only after commit. */
final readonly class ReviewCheckoutInvitationCoordinator implements CheckoutReviewInvitationCoordinator
{
    public function __construct(
        private ReviewInvitationServiceProvider $services,
        private ReviewInvitationDelivery $delivery,
        private LoggerInterface $logger,
    ) {
    }

    public function createDuringCheckout(
        int $bookingId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?IssuedReviewInvitation {
        return $this->services->service()->issueForBookingIfAbsentInCurrentTransaction(
            $bookingId,
            $administratorId,
            $ipAddress,
            $userAgent,
        );
    }

    public function deliverAfterCommit(#[SensitiveParameter] IssuedReviewInvitation $invitation): void
    {
        try {
            $this->delivery->deliver($invitation);
        } catch (Throwable) {
            $this->safeLog($invitation);
        }
    }

    private function safeLog(#[SensitiveParameter] IssuedReviewInvitation $invitation): void
    {
        try {
            $this->logger->warning('review_invitation_delivery_failed', [
                'booking_id' => $invitation->invitation()->bookingId(),
                'review_invitation_id' => $invitation->invitation()->id(),
            ]);
        } catch (Throwable) {
            error_log('Review invitation delivery logging failed.');
        }
    }
}
