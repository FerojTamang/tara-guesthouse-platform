<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use SensitiveParameter;

/** Carries a newly issued raw token only until post-commit delivery. */
final readonly class IssuedReviewInvitation
{
    public function __construct(
        private ReviewInvitation $invitation,
        #[SensitiveParameter]
        private string $token,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new InvalidArgumentException('Issued review token is invalid.');
        }
    }

    public function invitation(): ReviewInvitation { return $this->invitation; }

    public function token(): string { return $this->token; }
}
