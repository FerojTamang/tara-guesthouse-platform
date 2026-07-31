<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IssuedReviewInvitation;
use SensitiveParameter;

/** Defines post-commit delivery for one raw review invitation token. */
interface ReviewInvitationDelivery
{
    public function deliver(#[SensitiveParameter] IssuedReviewInvitation $invitation): void;
}
