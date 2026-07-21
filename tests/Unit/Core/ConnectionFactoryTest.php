<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ConnectionFactory;
use App\Exceptions\ConfigurationException;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testItBuildsTheExpectedPdoMysqlDsnWithoutCredentials(): void
    {
        $factory = new ConnectionFactory($this->configuration());

        self::assertSame(
            'mysql:host=127.0.0.1;port=3307;dbname=tara_guest_house_test;charset=utf8mb4',
            $factory->dsn(),
        );
        self::assertStringNotContainsString('tara_test', $factory->dsn());
        self::assertStringNotContainsString('secret-value', $factory->dsn());
    }

    public function testItRejectsMissingConfigurationKeys(): void
    {
        $configuration = $this->configuration();
        unset($configuration['options']);

        $this->expectException(ConfigurationException::class);
        new ConnectionFactory($configuration);
    }

    public function testItRejectsConfigurationThatBypassesStrictValidation(): void
    {
        $configuration = $this->configuration();
        $configuration['charset'] = 'utf8';

        $this->expectException(ConfigurationException::class);
        new ConnectionFactory($configuration);
    }

    public function testItRejectsDsnInjectionAndUnsafePdoOptionOverrides(): void
    {
        foreach ([
            ['host' => '127.0.0.1;dbname=unsafe'],
            ['database' => 'safe;port=1'],
            ['database' => 'safe=unsafe'],
            ['username' => 'unsafe;user'],
        ] as $override) {
            try {
                new ConnectionFactory(array_merge($this->configuration(), $override));
                self::fail('Unsafe direct connection configuration was accepted.');
            } catch (ConfigurationException) {
                self::assertTrue(true);
            }
        }

        $configuration = $this->configuration();
        $configuration['options'][PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;

        $this->expectException(ConfigurationException::class);
        new ConnectionFactory($configuration);
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3307,
            'database' => 'tara_guest_house_test',
            'username' => 'tara_test',
            'password' => 'secret-value',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'timezone' => '+00:00',
            'connect_timeout' => 5,
            'ssl_ca' => null,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ],
        ];
    }
}
