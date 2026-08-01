<?php

declare(strict_types=1);

namespace App\Services;

/** Provides the stream operations required by the SMTP protocol. */
interface SmtpConnection
{
    public function write(string $bytes): void;

    public function readLine(): string;

    public function enableCrypto(): void;

    public function close(): void;
}
