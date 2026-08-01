<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailException;

/** Opens SMTP sockets with peer verification enabled. */
final readonly class NativeSmtpConnectionFactory implements SmtpConnectionFactory
{
    public function connect(
        string $host,
        int $port,
        string $encryption,
        int $timeoutSeconds,
    ): SmtpConnection {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'disable_compression' => true,
            ],
        ]);
        $displayHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '[' . $host . ']'
            : $host;
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $stream = @stream_socket_client(
            sprintf('%s://%s:%d', $scheme, $displayHost, $port),
            $errorCode,
            $errorMessage,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (!is_resource($stream)) {
            throw MailException::transport();
        }

        if (!@stream_set_timeout($stream, $timeoutSeconds)) {
            @fclose($stream);
            throw MailException::transport();
        }

        return new StreamSmtpConnection($stream);
    }
}
