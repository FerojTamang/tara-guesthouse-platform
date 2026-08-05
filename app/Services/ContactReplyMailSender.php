<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactMessage;
use SensitiveParameter;

/** Sends one synchronous Administrator contact reply and reports acceptance. */
interface ContactReplyMailSender
{
    public function send(
        ContactMessage $contact,
        #[SensitiveParameter] string $replyBody,
    ): bool;
}
