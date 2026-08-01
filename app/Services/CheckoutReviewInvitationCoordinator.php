<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IssuedReviewInvitation;
use SensitiveParameter;

/** Coordinates review invitation work around a checked-out booking commit. */
interface CheckoutReviewInvitationCoordinator
{
    public function createDuringCheckout(
        int $bookingId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?IssuedReviewInvitation;

    public function deliverAfterCommit(#[SensitiveParameter] IssuedReviewInvitation $invitation): void;
}
