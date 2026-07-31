<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IssuedReviewInvitation;
use SensitiveParameter;

/** Defers delivery until the approved transactional mail ticket is installed. */
final readonly class NullReviewInvitationDelivery implements ReviewInvitationDelivery
{
    public function deliver(#[SensitiveParameter] IssuedReviewInvitation $invitation): void
    {
        // A concrete post-commit transport is installed by the mail-delivery workflow.
    }
}
