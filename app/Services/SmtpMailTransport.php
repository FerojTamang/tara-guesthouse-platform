<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailException;
use App\Models\MailMessage;
use DateTimeImmutable;
use DateTimeZone;
use SensitiveParameter;
use Throwable;

/** Sends prepared messages through one configured SMTP server. */
final readonly class SmtpMailTransport implements MailTransport
{
    private const MAX_RESPONSE_LINES = 100;
    private const MAX_RESPONSE_BYTES = 65536;

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private ?string $username,
        #[SensitiveParameter]
        private ?string $password,
        private string $fromAddress,
        private string $fromName,
        private ?string $replyToAddress,
        private ?string $replyToName,
        private int $timeoutSeconds,
        private ?SmtpConnectionFactory $connections = null,
    ) {
        if (!$this->validConfiguration()) {
            throw MailException::configuration();
        }
    }

    public function send(#[SensitiveParameter] MailMessage $message): void
    {
        $connection = null;
        $accepted = false;

        try {
            $connection = ($this->connections ?? new NativeSmtpConnectionFactory())->connect(
                $this->host,
                $this->port,
                $this->encryption,
                $this->timeoutSeconds,
            );
            $this->expect($connection, [220]);
            $this->command($connection, 'EHLO ' . $this->clientIdentity(), [250]);

            if ($this->encryption === 'tls') {
                $this->command($connection, 'STARTTLS', [220]);
                $connection->enableCrypto();
                $this->command($connection, 'EHLO ' . $this->clientIdentity(), [250]);
            }

            if ($this->username !== null && $this->password !== null) {
                $this->command($connection, 'AUTH LOGIN', [334]);
                $this->command($connection, base64_encode($this->username), [334]);
                $this->command($connection, base64_encode($this->password), [235]);
            }

            $this->command($connection, 'MAIL FROM:<' . $this->fromAddress . '>', [250]);
            $this->command(
                $connection,
                'RCPT TO:<' . $message->recipientAddress() . '>',
                [250, 251],
            );
            $this->command($connection, 'DATA', [354]);
            $connection->write($this->encodeMessage($message) . "\r\n.\r\n");
            $this->expect($connection, [250]);
            $accepted = true;

            try {
                $this->command($connection, 'QUIT', [221]);
            } catch (Throwable) {
                // DATA acceptance is authoritative; a QUIT failure must not reclassify delivery.
            }
        } catch (MailException $exception) {
            if ($accepted) {
                return;
            }

            throw $exception->isConfigurationFailure()
                ? $exception
                : MailException::transport();
        } catch (Throwable) {
            if ($accepted) {
                return;
            }

            throw MailException::transport();
        } finally {
            if ($connection instanceof SmtpConnection) {
                try {
                    $connection->close();
                } catch (Throwable) {
                    // Socket cleanup failure cannot reveal details or reclassify DATA acceptance.
                }
            }
        }
    }

    /** @param list<int> $expectedCodes */
    private function command(
        SmtpConnection $connection,
        #[SensitiveParameter] string $command,
        array $expectedCodes,
    ): void {
        if (
            $command === ''
            || str_contains($command, "\r")
            || str_contains($command, "\n")
            || str_contains($command, "\0")
        ) {
            throw MailException::transport();
        }

        $connection->write($command . "\r\n");
        $this->expect($connection, $expectedCodes);
    }

    /** @param list<int> $expectedCodes */
    private function expect(SmtpConnection $connection, array $expectedCodes): void
    {
        $bytes = 0;
        $code = null;

        for ($lineNumber = 0; $lineNumber < self::MAX_RESPONSE_LINES; $lineNumber++) {
            $line = $connection->readLine();
            $bytes += strlen($line);

            if ($bytes > self::MAX_RESPONSE_BYTES) {
                throw MailException::transport();
            }

            if (preg_match('/^([0-9]{3})([ -])[^\r\n]*\r?\n$/D', $line, $matches) !== 1) {
                throw MailException::transport();
            }

            $lineCode = (int) $matches[1];
            $code ??= $lineCode;

            if ($lineCode !== $code) {
                throw MailException::transport();
            }

            if ($matches[2] === ' ') {
                if (!in_array($code, $expectedCodes, true)) {
                    throw MailException::transport();
                }

                return;
            }
        }

        throw MailException::transport();
    }

    private function encodeMessage(MailMessage $message): string
    {
        try {
            $boundary = '=_TGH_' . bin2hex(random_bytes(18));
            $domain = $this->addressDomain($this->fromAddress);
            $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(18)), $domain);
            $headers = [
                'Date: ' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_RFC2822),
                'Message-ID: ' . $messageId,
                'From: ' . $this->mailbox($this->fromAddress, $this->fromName),
                'To: ' . $this->mailbox($message->recipientAddress(), $message->recipientName()),
                'Subject: ' . $this->encodedHeader($message->subject()),
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ];

            if ($this->replyToAddress !== null) {
                $headers[] = 'Reply-To: ' . $this->mailbox(
                    $this->replyToAddress,
                    $this->replyToName ?? '',
                );
            }

            $body = [
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                $this->quotedPrintable($message->textBody()),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                $this->quotedPrintable($message->htmlBody()),
                '--' . $boundary . '--',
            ];

            $encoded = implode("\r\n", [...$headers, '', ...$body]);
            $normalized = preg_replace('/(?<!\r)\n/', "\r\n", str_replace("\r\n", "\n", $encoded));

            if (!is_string($normalized) || str_contains($normalized, "\0")) {
                throw MailException::transport();
            }

            return preg_replace('/^\./m', '..', $normalized) ?? throw MailException::transport();
        } catch (MailException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw MailException::transport();
        }
    }

    private function mailbox(string $address, string $name): string
    {
        return $name === ''
            ? '<' . $address . '>'
            : $this->encodedHeader($name) . ' <' . $address . '>';
    }

    private function encodedHeader(string $value): string
    {
        $encoded = mb_encode_mimeheader($value, 'UTF-8', 'Q', "\r\n", 0);

        if (
            !is_string($encoded)
            || $encoded === ''
            || preg_match('/\r\n(?![ \t])/', $encoded) === 1
        ) {
            throw MailException::transport();
        }

        return $encoded;
    }

    private function quotedPrintable(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $encoded = quoted_printable_encode($normalized);

        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $encoded));
    }

    private function clientIdentity(): string
    {
        $domain = $this->addressDomain($this->fromAddress);

        return preg_match('/^[A-Za-z0-9.-]+$/D', $domain) === 1
            ? $domain
            : 'localhost';
    }

    private function addressDomain(string $address): string
    {
        $position = strrpos($address, '@');

        if ($position === false) {
            throw MailException::configuration();
        }

        return substr($address, $position + 1);
    }

    private function validConfiguration(): bool
    {
        $hostValid = filter_var($this->host, FILTER_VALIDATE_IP) !== false
            || preg_match(
                '/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/D',
                $this->host,
            ) === 1;
        $credentialsValid = ($this->username === null && $this->password === null)
            || (
                $this->username !== null
                && $this->password !== null
                && $this->validUsername($this->username)
                && $this->validPassword($this->password)
                && $this->encryption !== 'none'
            );
        $replyToValid = $this->replyToAddress === null
            ? $this->replyToName === null
            : $this->validAddress($this->replyToAddress)
                && $this->validName($this->replyToName ?? '', true);

        return $hostValid
            && $this->host === trim($this->host)
            && !str_contains($this->host, "\0")
            && $this->port >= 1
            && $this->port <= 65535
            && in_array($this->encryption, ['tls', 'ssl', 'none'], true)
            && $credentialsValid
            && $this->validAddress($this->fromAddress)
            && $this->validName($this->fromName, false)
            && $replyToValid
            && $this->timeoutSeconds >= 1
            && $this->timeoutSeconds <= 60;
    }

    private function validUsername(string $value): bool
    {
        return $value !== ''
            && $value === trim($value)
            && strlen($value) <= 512
            && preg_match('/[\r\n\x00]/', $value) !== 1;
    }

    private function validPassword(#[SensitiveParameter] string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 512
            && preg_match('/[\r\n\x00]/', $value) !== 1;
    }

    private function validAddress(string $value): bool
    {
        return $value === trim($value)
            && strlen($value) <= 254
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/[\r\n\x00]/', $value) !== 1;
    }

    private function validName(string $value, bool $allowEmpty): bool
    {
        return ($allowEmpty || $value !== '')
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= 150
            && preg_match('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 1;
    }
}
