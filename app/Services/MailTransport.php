<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailMessage;
use SensitiveParameter;

/** Sends one prepared transactional email. */
interface MailTransport
{
    public function send(#[SensitiveParameter] MailMessage $message): void;
}
