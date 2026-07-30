<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\ConnectionFactory;
use App\Core\Environment;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $connection;

    /** @var array<string, mixed> */
    protected array $databaseConfiguration;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        $environment = Environment::load($basePath);
        $testKeys = [
            'TEST_DB_DRIVER',
            'TEST_DB_HOST',
            'TEST_DB_PORT',
            'TEST_DB_DATABASE',
            'TEST_DB_USERNAME',
            'TEST_DB_PASSWORD',
            'TEST_DB_CHARSET',
            'TEST_DB_COLLATION',
            'TEST_DB_TIMEZONE',
            'TEST_DB_CONNECT_TIMEOUT',
            'TEST_DB_SSL_CA',
        ];
        $configuredTestKeys = array_filter($testKeys, $environment->has(...));

        if ($configuredTestKeys === []) {
            self::markTestSkipped('An isolated test database is not configured.');
        }

        $this->databaseConfiguration = Config::loadDatabase(
            $basePath . DIRECTORY_SEPARATOR . 'config',
            $environment,
        );

        self::assertMatchesRegularExpression('/_(?:test|testing)$/D', $this->databaseConfiguration['database']);
        self::assertNotSame($environment->literalString('DB_DATABASE'), $this->databaseConfiguration['database']);
        $this->connection = (new ConnectionFactory($this->databaseConfiguration))->create();
        self::assertSame($this->databaseConfiguration['database'], $this->connection->query('SELECT DATABASE()')->fetchColumn());
        $this->cleanupApplicationTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->cleanupApplicationTables();
        }

        parent::tearDown();
    }

    protected function cleanupApplicationTables(): void
    {
        $this->dropTables([
            'invoice_items',
            'invoices',
            'payments',
            'payment_methods',
            'audit_logs',
            'booking_events',
            'booking_rooms',
            'bookings',
            'room_amenities',
            'room_images',
            'rooms',
            'amenities',
            'room_types',
            'users',
            'roles',
            'schema_migrations',
        ]);
    }

    /** @param list<string> $tables */
    protected function dropTables(array $tables): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                if (preg_match('/^[a-z][a-z0-9_]*$/D', $table) !== 1) {
                    self::fail('Unsafe test table identifier.');
                }

                $this->connection->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
            }
        } finally {
            $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
