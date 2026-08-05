<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactMessage;
use SensitiveParameter;

/** Disables contact-reply delivery when SMTP is not configured. */
final readonly class NullContactReplyMailSender implements ContactReplyMailSender
{
    public function send(
        ContactMessage $contact,
        #[SensitiveParameter] string $replyBody,
    ): bool {
        return false;
    }
}
