<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionConfigTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'unsafe session name' => ['SESSION_NAME', '123 invalid'];
        yield 'session name surrounding whitespace' => ['SESSION_NAME', ' tgh_session'];
        yield 'relative cookie path' => ['SESSION_PATH', 'admin'];
        yield 'cookie path leading whitespace' => ['SESSION_PATH', ' /'];
        yield 'cookie path delimiter' => ['SESSION_PATH', '/admin; Secure'];
        yield 'domain scheme' => ['SESSION_DOMAIN', 'https://example.com'];
        yield 'domain port' => ['SESSION_DOMAIN', 'example.com:443'];
        yield 'domain path' => ['SESSION_DOMAIN', 'example.com/admin'];
        yield 'domain wildcard' => ['SESSION_DOMAIN', '*.example.com'];
        yield 'domain surrounding whitespace' => ['SESSION_DOMAIN', ' example.com'];
    }

    public function testItUsesExactApprovedDefaultsAndFixedSecurityInvariants(): void
    {
        $configuration = $this->load([
            'SESSION_INACTIVITY_TIMEOUT' => '',
            'SESSION_ABSOLUTE_LIFETIME' => '',
        ]);

        self::assertSame(1800, $configuration['inactivity_timeout']);
        self::assertSame(43200, $configuration['absolute_lifetime']);
        self::assertSame(0, $configuration['cookie']['lifetime']);
        self::assertFalse($configuration['cookie']['secure']);
        self::assertTrue($configuration['cookie']['http_only']);
        self::assertSame('Lax', $configuration['cookie']['same_site']);
        self::assertSame('/', $configuration['cookie']['path']);
        self::assertNull($configuration['cookie']['domain']);
        self::assertTrue($configuration['strict_mode']);
        self::assertTrue($configuration['use_cookies']);
        self::assertTrue($configuration['use_only_cookies']);
        self::assertFalse($configuration['use_trans_sid']);
        self::assertSame('files', $configuration['save_handler']);
        self::assertSame(realpath(STORAGE_PATH . DIRECTORY_SEPARATOR . 'sessions'), $configuration['save_path']);
        self::assertSame(
            realpath(STORAGE_PATH) . DIRECTORY_SEPARATOR . 'sessions',
            $configuration['save_path'],
        );
        self::assertGreaterThanOrEqual(43200, $configuration['gc_maxlifetime']);
        self::assertFalse(str_starts_with($configuration['save_path'], (string) realpath(PUBLIC_PATH)));
    }

    public function testStrictBooleanParsingRejectsInvalidValues(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['SESSION_SECURE_COOKIE' => 'sometimes']);
    }

    public function testBooleanWhitespaceIsNotSilentlyTrimmed(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['SESSION_SECURE_COOKIE' => ' false ']);
    }

    public function testIntegerWhitespaceIsNotSilentlyTrimmed(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['SESSION_INACTIVITY_TIMEOUT' => ' 1800']);
    }

    public function testProductionRejectsBlankTimeouts(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load([
            'APP_ENV' => 'production',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_INACTIVITY_TIMEOUT' => '',
        ]);
    }

    public function testProductionRejectsInsecureCookies(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load([
            'APP_ENV' => 'production',
            'SESSION_SECURE_COOKIE' => 'false',
        ]);
    }

    public function testProductionAcceptsApprovedSecureConfiguration(): void
    {
        $configuration = $this->load([
            'APP_ENV' => 'production',
            'SESSION_SECURE_COOKIE' => 'true',
        ]);

        self::assertTrue($configuration['cookie']['secure']);
    }

    public function testHttpOnlyCannotBeDisabled(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['SESSION_HTTP_ONLY' => 'false']);
    }

    public function testInvalidSameSiteIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['SESSION_SAME_SITE' => 'None']);
    }

    public function testSameSiteCaseIsNotSilentlyNormalized(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(['SESSION_SAME_SITE' => 'lax']);
    }

    public function testStagingRejectsBlankAbsoluteLifetime(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load([
            'APP_ENV' => 'staging',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_ABSOLUTE_LIFETIME' => '',
        ]);
    }

    #[DataProvider('invalidValues')]
    public function testInvalidNamePathAndDomainValuesAreRejected(string $key, string $value): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load([$key => $value]);
    }

    public function testAbsoluteLifetimeCannotBeShorterThanInactivityTimeout(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load([
            'SESSION_INACTIVITY_TIMEOUT' => '1800',
            'SESSION_ABSOLUTE_LIFETIME' => '1799',
        ]);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, mixed>
     */
    private function load(array $overrides = []): array
    {
        $basePath = dirname(__DIR__, 3);

        if (!defined('STORAGE_PATH')) {
            define('STORAGE_PATH', $basePath . DIRECTORY_SEPARATOR . 'storage');
        }

        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', $basePath . DIRECTORY_SEPARATOR . 'public');
        }

        $environment = Environment::fromArray(array_merge([
            'APP_ENV' => 'local',
            'SESSION_NAME' => 'tgh_session',
            'SESSION_INACTIVITY_TIMEOUT' => '1800',
            'SESSION_ABSOLUTE_LIFETIME' => '43200',
            'SESSION_SECURE_COOKIE' => 'false',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'Lax',
            'SESSION_PATH' => '/',
            'SESSION_DOMAIN' => '',
        ], $overrides));

        /** @var array<string, mixed> $configuration */
        $configuration = require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'session.php';

        return $configuration;
    }
}
