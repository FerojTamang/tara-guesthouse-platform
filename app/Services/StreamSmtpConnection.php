<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailException;

/** Wraps one native PHP stream without exposing transport details. */
final class StreamSmtpConnection implements SmtpConnection
{
    /** @param resource $stream */
    public function __construct(private $stream)
    {
        if (!is_resource($stream)) {
            throw MailException::transport();
        }
    }

    public function write(string $bytes): void
    {
        $length = strlen($bytes);
        $written = 0;

        while ($written < $length) {
            $result = @fwrite($this->stream, substr($bytes, $written));

            if (!is_int($result) || $result <= 0) {
                throw MailException::transport();
            }

            $written += $result;
        }
    }

    public function readLine(): string
    {
        $line = @fgets($this->stream, 4097);
        $metadata = @stream_get_meta_data($this->stream);

        if (
            !is_string($line)
            || strlen($line) > 4096
            || (is_array($metadata) && ($metadata['timed_out'] ?? false) === true)
        ) {
            throw MailException::transport();
        }

        return $line;
    }

    public function enableCrypto(): void
    {
        $enabled = @stream_socket_enable_crypto(
            $this->stream,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT,
        );

        if ($enabled !== true) {
            throw MailException::transport();
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }
    }
}
