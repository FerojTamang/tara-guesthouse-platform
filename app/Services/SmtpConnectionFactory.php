<?php

declare(strict_types=1);

namespace App\Services;

/** Opens one verified SMTP connection. */
interface SmtpConnectionFactory
{
    public function connect(
        string $host,
        int $port,
        string $encryption,
        int $timeoutSeconds,
    ): SmtpConnection;
}
