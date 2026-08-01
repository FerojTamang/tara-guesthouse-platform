<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailConfigTest extends TestCase
{
    public function testTestingEnvironmentMayDisableSmtpCompletely(): void
    {
        $configuration = $this->load(['APP_ENV' => 'testing']);

        self::assertSame([
            'enabled' => false,
            'host' => null,
            'port' => 587,
            'encryption' => 'tls',
            'username' => null,
            'password' => null,
            'from_address' => null,
            'from_name' => 'Tara Guest House',
            'reply_to_address' => null,
            'reply_to_name' => null,
            'timeout_seconds' => 10,
        ], $configuration);
    }

    public function testCompleteSmtpConfigurationIsNormalizedWithoutExposingDefaults(): void
    {
        $configuration = $this->load([
            'APP_ENV' => 'production',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_PORT' => '465',
            'MAIL_USERNAME' => 'mailer@example.test',
            'MAIL_PASSWORD' => 'private-password',
            'MAIL_ENCRYPTION' => 'SSL',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_FROM_NAME' => 'Tara Guest House',
            'MAIL_REPLY_TO_ADDRESS' => 'frontdesk@example.test',
            'MAIL_REPLY_TO_NAME' => 'Front Desk',
            'MAIL_TIMEOUT' => '15',
        ]);

        self::assertTrue($configuration['enabled']);
        self::assertSame('smtp.example.test', $configuration['host']);
        self::assertSame(465, $configuration['port']);
        self::assertSame('ssl', $configuration['encryption']);
        self::assertSame('mailer@example.test', $configuration['username']);
        self::assertSame('private-password', $configuration['password']);
        self::assertSame('stay@example.test', $configuration['from_address']);
        self::assertSame('Front Desk', $configuration['reply_to_name']);
        self::assertSame(15, $configuration['timeout_seconds']);
    }

    public function testPasswordIsTreatedAsAnOpaqueSecret(): void
    {
        $configuration = $this->load([
            'APP_ENV' => 'testing',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_USERNAME' => 'mailer@example.test',
            'MAIL_PASSWORD' => ' private password ',
            'MAIL_ENCRYPTION' => 'tls',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
        ]);

        self::assertSame(' private password ', $configuration['password']);
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidOrPartialConfigurationFailsClosed(array $values): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['APP_ENV' => 'testing', ...$values]);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'production without host' => [[
            'APP_ENV' => 'production',
        ]];
        yield 'password without username' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_PASSWORD' => 'private-password',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
        ]];
        yield 'username without password' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_USERNAME' => 'mailer@example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
        ]];
        yield 'scheme in host' => [[
            'MAIL_HOST' => 'smtp://example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
        ]];
        yield 'header injection in sender name' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_FROM_NAME' => "Tara\r\nBcc: attacker@example.test",
        ]];
        yield 'reply name without address' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_REPLY_TO_NAME' => 'Front Desk',
        ]];
        yield 'production transport without encryption' => [[
            'APP_ENV' => 'production',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_ENCRYPTION' => 'none',
        ]];
        yield 'authentication without encryption' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_USERNAME' => 'mailer@example.test',
            'MAIL_PASSWORD' => 'private-password',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_ENCRYPTION' => 'none',
        ]];
        yield 'invalid encryption' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_ENCRYPTION' => 'starttls',
        ]];
        yield 'invalid port' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_PORT' => '70000',
        ]];
        yield 'invalid timeout' => [[
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
            'MAIL_TIMEOUT' => '61',
        ]];
    }

    /** @param array<string, string> $values */
    private function load(array $values): array
    {
        $environment = Environment::fromArray($values);

        return require dirname(__DIR__, 3) . '/config/mail.php';
    }
}
