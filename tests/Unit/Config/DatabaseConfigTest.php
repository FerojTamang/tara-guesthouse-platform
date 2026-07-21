<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Config;
use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    public function testItLoadsTheApprovedStrictConfigurationAndPdoOptions(): void
    {
        $configuration = $this->load($this->localValues());

        self::assertSame('mysql', $configuration['driver']);
        self::assertSame('127.0.0.1', $configuration['host']);
        self::assertSame(3307, $configuration['port']);
        self::assertSame('tara_guest_house', $configuration['database']);
        self::assertSame('tara_local', $configuration['username']);
        self::assertSame('', $configuration['password']);
        self::assertSame('utf8mb4', $configuration['charset']);
        self::assertSame('utf8mb4_unicode_ci', $configuration['collation']);
        self::assertSame('+00:00', $configuration['timezone']);
        self::assertSame(5, $configuration['connect_timeout']);
        self::assertNull($configuration['ssl_ca']);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $configuration['options'][PDO::ATTR_ERRMODE]);
        self::assertSame(PDO::FETCH_ASSOC, $configuration['options'][PDO::ATTR_DEFAULT_FETCH_MODE]);
        self::assertFalse($configuration['options'][PDO::ATTR_EMULATE_PREPARES]);
        self::assertFalse($configuration['options'][PDO::ATTR_STRINGIFY_FETCHES]);
        self::assertFalse($configuration['options'][PDO::ATTR_PERSISTENT]);
        self::assertSame(5, $configuration['options'][PDO::ATTR_TIMEOUT]);
        self::assertFalse($configuration['options'][PDO::MYSQL_ATTR_MULTI_STATEMENTS]);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidHostValues(): iterable
    {
        yield 'empty' => [['DB_HOST' => '']];
        yield 'space' => [['DB_HOST' => '127.0.0.1 local']];
        yield 'surrounding whitespace' => [['DB_HOST' => ' 127.0.0.1']];
        yield 'control' => [['DB_HOST' => "127.0.0.1\n"]];
        yield 'unicode whitespace' => [['DB_HOST' => "127.0.0.1\u{00A0}"]];
        yield 'dsn semicolon' => [['DB_HOST' => '127.0.0.1;dbname=unsafe']];
        yield 'dsn equals' => [['DB_HOST' => 'host=unsafe']];
    }

    /** @param array<string, string> $override */
    #[DataProvider('invalidHostValues')]
    public function testItRejectsUnsafeHosts(array $override): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->localValues(), $override));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPorts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'too large' => ['65536'];
        yield 'decimal' => ['3307.0'];
        yield 'suffix' => ['3307x'];
        yield 'surrounding whitespace' => [' 3307'];
    }

    #[DataProvider('invalidPorts')]
    public function testItRejectsInvalidPorts(string $port): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->localValues(), ['DB_PORT' => $port]));
    }

    public function testItRejectsEmptyOrUnsafeDatabaseAndUsernameValues(): void
    {
        foreach ([
            ['DB_DATABASE' => ''],
            ['DB_DATABASE' => 'unsafe;value'],
            ['DB_DATABASE' => 'unsafe=value'],
            ['DB_USERNAME' => ''],
            ['DB_USERNAME' => ' user'],
            ['DB_USERNAME' => 'unsafe;user'],
            ['DB_USERNAME' => 'unsafe=user'],
        ] as $override) {
            try {
                $this->load(array_merge($this->localValues(), $override));
                self::fail('Invalid database identity value was accepted.');
            } catch (ConfigurationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testPasswordRemainsLiteralAndBlankIsAllowedLocally(): void
    {
        $configuration = $this->load(array_merge($this->localValues(), ['DB_PASSWORD' => ' literal password ']));

        self::assertSame(' literal password ', $configuration['password']);
        self::assertSame('', $this->load($this->localValues())['password']);
    }

    public function testProtectedEnvironmentsRequireAPassword(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->localValues(), ['APP_ENV' => 'production']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFixedSettings(): iterable
    {
        yield 'driver' => ['DB_DRIVER', 'pgsql'];
        yield 'charset case' => ['DB_CHARSET', 'UTF8MB4'];
        yield 'charset other' => ['DB_CHARSET', 'utf8'];
        yield 'collation' => ['DB_COLLATION', 'utf8mb4_general_ci'];
        yield 'timezone' => ['DB_TIMEZONE', 'Asia/Kathmandu'];
        yield 'timeout' => ['DB_CONNECT_TIMEOUT', '6'];
        yield 'timeout whitespace' => ['DB_CONNECT_TIMEOUT', ' 5'];
    }

    #[DataProvider('invalidFixedSettings')]
    public function testItRejectsUnapprovedFixedSettings(string $key, string $value): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->localValues(), [$key => $value]));
    }

    public function testTestingUsesOnlyTheDistinctSuffixedTestDatabase(): void
    {
        $configuration = $this->load($this->testingValues());

        self::assertSame('tara_guest_house_test', $configuration['database']);
        self::assertSame('tara_test', $configuration['username']);
    }

    public function testTestingNeverFallsBackToDevelopmentConnectionValues(): void
    {
        $values = $this->testingValues();
        unset($values['TEST_DB_HOST']);
        $values['DB_HOST'] = 'development-only-host';

        $this->expectException(ConfigurationException::class);
        $this->load($values);
    }

    public function testTestingRejectsANameWithoutTheRequiredSuffix(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->testingValues(), ['TEST_DB_DATABASE' => 'tara_guest_house_ci']));
    }

    public function testTestingRejectsTheDevelopmentDatabase(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->testingValues(), ['TEST_DB_DATABASE' => 'tara_guest_house']));
    }

    public function testTestingRequiresAValidDevelopmentDatabaseGuardName(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->testingValues(), ['DB_DATABASE' => '']));
    }

    public function testSslCaMustBeAnExistingReadableFile(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load(array_merge($this->localValues(), ['DB_SSL_CA' => __DIR__ . '/missing-ca.pem']));
    }

    public function testExistingSslCaIsResolvedAndAddedToPdoOptions(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tgh-ca-');
        self::assertIsString($path);

        try {
            $configuration = $this->load(array_merge($this->localValues(), ['DB_SSL_CA' => $path]));
            self::assertSame(realpath($path), $configuration['ssl_ca']);
            self::assertSame(realpath($path), $configuration['options'][PDO::MYSQL_ATTR_SSL_CA]);
        } finally {
            unlink($path);
        }
    }

    /** @return array<string, string> */
    private function localValues(): array
    {
        return [
            'APP_ENV' => 'local',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3307',
            'DB_DATABASE' => 'tara_guest_house',
            'DB_USERNAME' => 'tara_local',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_unicode_ci',
            'DB_TIMEZONE' => '+00:00',
            'DB_CONNECT_TIMEOUT' => '5',
            'DB_SSL_CA' => '',
        ];
    }

    /** @return array<string, string> */
    private function testingValues(): array
    {
        return array_merge($this->localValues(), [
            'APP_ENV' => 'testing',
            'TEST_DB_DRIVER' => 'mysql',
            'TEST_DB_HOST' => '127.0.0.1',
            'TEST_DB_PORT' => '3307',
            'TEST_DB_DATABASE' => 'tara_guest_house_test',
            'TEST_DB_USERNAME' => 'tara_test',
            'TEST_DB_PASSWORD' => '',
            'TEST_DB_CHARSET' => 'utf8mb4',
            'TEST_DB_COLLATION' => 'utf8mb4_unicode_ci',
            'TEST_DB_TIMEZONE' => '+00:00',
            'TEST_DB_CONNECT_TIMEOUT' => '5',
            'TEST_DB_SSL_CA' => '',
        ]);
    }

    /**
     * @param array<string, string> $values
     * @return array<string, mixed>
     */
    private function load(array $values): array
    {
        return Config::loadDatabase(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config',
            Environment::fromArray($values),
        );
    }
}
