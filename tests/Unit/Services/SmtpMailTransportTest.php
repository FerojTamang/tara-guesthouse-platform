<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\MailException;
use App\Models\MailMessage;
use App\Services\SmtpConnection;
use App\Services\SmtpConnectionFactory;
use App\Services\SmtpMailTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmtpMailTransportTest extends TestCase
{
    public function testTlsAuthenticationAndMultipartDeliveryUseTheApprovedProtocol(): void
    {
        $connection = new ScriptedSmtpConnection([
            "220 smtp.example.test ready\r\n",
            "250-smtp.example.test\r\n",
            "250 STARTTLS\r\n",
            "220 begin tls\r\n",
            "250-smtp.example.test\r\n",
            "250 AUTH LOGIN\r\n",
            "334 VXNlcm5hbWU6\r\n",
            "334 UGFzc3dvcmQ6\r\n",
            "235 authenticated\r\n",
            "250 sender accepted\r\n",
            "251 recipient forwarded\r\n",
            "354 send data\r\n",
            "250 queued\r\n",
            "221 goodbye\r\n",
        ]);
        $factory = new ScriptedSmtpConnectionFactory($connection);
        $transport = new SmtpMailTransport(
            'smtp.example.test',
            587,
            'tls',
            'mailer@example.test',
            ' private-password ',
            'stay@example.test',
            'Tara Guest House',
            'frontdesk@example.test',
            'Front Desk',
            10,
            $factory,
        );

        $transport->send(new MailMessage(
            'guest@example.test',
            'Guest Example',
            'Booking received',
            ".Leading line\nSecond line",
            '<p>Message body</p>',
        ));

        self::assertSame('smtp.example.test', $factory->host);
        self::assertSame(587, $factory->port);
        self::assertSame('tls', $factory->encryption);
        self::assertSame(10, $factory->timeout);
        self::assertSame(1, $connection->cryptoCalls);
        self::assertSame('EHLO example.test', trim($connection->writes[0]));
        self::assertSame('STARTTLS', trim($connection->writes[1]));
        self::assertSame('AUTH LOGIN', trim($connection->writes[3]));
        self::assertSame(base64_encode('mailer@example.test'), trim($connection->writes[4]));
        self::assertSame(base64_encode(' private-password '), trim($connection->writes[5]));
        self::assertSame('MAIL FROM:<stay@example.test>', trim($connection->writes[6]));
        self::assertSame('RCPT TO:<guest@example.test>', trim($connection->writes[7]));
        self::assertSame('DATA', trim($connection->writes[8]));
        $messageBytes = $connection->writes[9];
        self::assertStringContainsString('Content-Type: multipart/alternative;', $messageBytes);
        self::assertStringContainsString('Reply-To: Front Desk <frontdesk@example.test>', $messageBytes);
        self::assertStringContainsString("\r\n..Leading line", $messageBytes);
        self::assertStringContainsString("\r\n.\r\n", $messageBytes);
        self::assertStringNotContainsString('private-password', $messageBytes);
        self::assertSame('QUIT', trim($connection->writes[10]));
        self::assertSame(1, $connection->closeCalls);
    }

    public function testAuthenticationWithoutEncryptionFailsClosed(): void
    {
        try {
            new SmtpMailTransport(
                'smtp.example.test',
                25,
                'none',
                'mailer@example.test',
                'private-password',
                'stay@example.test',
                'Tara Guest House',
                null,
                null,
                10,
            );
            self::fail('Unencrypted SMTP authentication was accepted.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isConfigurationFailure());
            self::assertSame('Transactional email is not configured.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testTransportFailureIsGenericDetachedAndClosesTheConnection(): void
    {
        $connection = new ScriptedSmtpConnection([
            "220 ready\r\n",
            "550 private server failure\r\n",
        ]);
        $transport = $this->transport($connection);

        try {
            $transport->send($this->message());
            self::fail('Expected SMTP failure.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isTransportFailure());
            self::assertSame('Transactional email delivery failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('private server failure', $exception->getMessage());
        }

        self::assertSame(1, $connection->closeCalls);
    }

    public function testQuitFailureAfterDataAcceptanceDoesNotReclassifyDelivery(): void
    {
        $connection = new ScriptedSmtpConnection([
            "220 ready\r\n",
            "250 hello\r\n",
            "250 sender\r\n",
            "250 recipient\r\n",
            "354 data\r\n",
            "250 queued\r\n",
            "500 quit unavailable\r\n",
        ]);

        $this->transport($connection)->send($this->message());

        self::assertSame(1, $connection->closeCalls);
        self::assertSame('QUIT', trim($connection->writes[array_key_last($connection->writes)]));
    }


    public function testConnectionCloseFailureAfterDataAcceptanceDoesNotReclassifyDelivery(): void
    {
        $connection = new ScriptedSmtpConnection([
            "220 ready\r\n",
            "250 hello\r\n",
            "250 sender\r\n",
            "250 recipient\r\n",
            "354 data\r\n",
            "250 queued\r\n",
            "221 goodbye\r\n",
        ]);
        $connection->closeFailure = new RuntimeException('private close failure');

        $this->transport($connection)->send($this->message());

        self::assertSame(1, $connection->closeCalls);
    }

    private function transport(ScriptedSmtpConnection $connection): SmtpMailTransport
    {
        return new SmtpMailTransport(
            'smtp.example.test',
            25,
            'none',
            null,
            null,
            'stay@example.test',
            'Tara Guest House',
            null,
            null,
            10,
            new ScriptedSmtpConnectionFactory($connection),
        );
    }

    private function message(): MailMessage
    {
        return new MailMessage(
            'guest@example.test',
            'Guest Example',
            'Status update',
            'Plain body',
            '<p>HTML body</p>',
        );
    }
}

final class ScriptedSmtpConnectionFactory implements SmtpConnectionFactory
{
    public ?string $host = null;
    public ?int $port = null;
    public ?string $encryption = null;
    public ?int $timeout = null;

    public function __construct(private readonly ScriptedSmtpConnection $connection)
    {
    }

    public function connect(
        string $host,
        int $port,
        string $encryption,
        int $timeoutSeconds,
    ): SmtpConnection {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = $encryption;
        $this->timeout = $timeoutSeconds;

        return $this->connection;
    }
}

final class ScriptedSmtpConnection implements SmtpConnection
{
    /** @var list<string> */
    public array $writes = [];
    public int $cryptoCalls = 0;
    public int $closeCalls = 0;
    public ?\Throwable $closeFailure = null;

    /** @param list<string> $responses */
    public function __construct(private array $responses)
    {
    }

    public function write(string $bytes): void
    {
        $this->writes[] = $bytes;
    }

    public function readLine(): string
    {
        $line = array_shift($this->responses);

        if (!is_string($line)) {
            throw new RuntimeException('SMTP test response script was exhausted.');
        }

        return $line;
    }

    public function enableCrypto(): void
    {
        $this->cryptoCalls++;
    }

    public function close(): void
    {
        $this->closeCalls++;

        if ($this->closeFailure !== null) {
            throw $this->closeFailure;
        }
    }
}
