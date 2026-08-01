<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Core\ConnectionFactory;
use App\Core\Environment;
use App\Core\MigrationRunner;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('database')]
final class DatabaseCommandTest extends TestCase
{
    private string $basePath;

    private PDO $connection;

    /** @var array<string, string> */
    private array $testingEnvironment;

    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = dirname(__DIR__, 2);
        $environment = Environment::load($this->basePath);
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

        $configuration = Config::loadDatabase($this->basePath . DIRECTORY_SEPARATOR . 'config', $environment);
        self::assertMatchesRegularExpression('/_(?:test|testing)$/D', $configuration['database']);
        self::assertNotSame($environment->literalString('DB_DATABASE'), $configuration['database']);
        $this->connection = (new ConnectionFactory($configuration))->create();
        $this->testingEnvironment = $this->childEnvironment($environment, 'TEST_DB_');
        $this->testingEnvironment['APP_ENV'] = 'testing';
        $this->cleanupDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtureFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (isset($this->connection)) {
            $this->cleanupDatabase();
        }

        parent::tearDown();
    }

    public function testStatusMigrateRollbackAndRepeatedMigrateSucceed(): void
    {
        $this->writeMigrationFixture();
        $migrationCount = $this->migrationCount();

        $status = $this->command(['status']);
        self::assertSame(0, $status['exit']);
        self::assertStringContainsString(
            sprintf('Pending migrations: %d', $migrationCount),
            $status['stdout'],
        );

        $migrate = $this->command(['migrate']);
        self::assertSame(0, $migrate['exit']);
        self::assertStringContainsString(
            sprintf('Applied migrations: %d', $migrationCount),
            $migrate['stdout'],
        );

        $repeat = $this->command(['migrate']);
        self::assertSame(0, $repeat['exit']);
        self::assertStringContainsString('Applied migrations: 0', $repeat['stdout']);

        $rollback = $this->command(['rollback']);
        self::assertSame(0, $rollback['exit']);
        self::assertStringContainsString(
            sprintf('Rolled back migrations: %d', $migrationCount),
            $rollback['stdout'],
        );

        $finalMigrate = $this->command(['migrate']);
        self::assertSame(0, $finalMigrate['exit']);
        self::assertStringContainsString(
            sprintf('Applied migrations: %d', $migrationCount),
            $finalMigrate['stdout'],
        );
    }

    public function testApprovedTestingSeederGroupSucceeds(): void
    {
        $this->connection->exec(
            'CREATE TABLE s0004_command_seed (id INT UNSIGNED NOT NULL PRIMARY KEY, value_name VARCHAR(32) NOT NULL UNIQUE) ENGINE=InnoDB',
        );
        $this->writeSeederFixture();

        $result = $this->command(['seed', '--group=testing']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Executed seeders: 1', $result['stdout']);
        self::assertSame('test-only', $this->connection->query('SELECT value_name FROM s0004_command_seed')->fetchColumn());
    }

    public function testUnknownCommandReturnsNonzero(): void
    {
        $result = $this->command(['unknown']);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Unknown database command.', $result['stderr']);
    }

    public function testDuplicateConflictingAndCommandInappropriateFlagsAreRejected(): void
    {
        foreach ([
            ['migrate', '--force', '--force'],
            ['seed', '--group=testing', '--group=development'],
            ['status', '--force'],
            ['rollback', '--group=testing'],
            ['migrate', '--group=testing'],
            ['seed', '--force'],
        ] as $arguments) {
            $result = $this->command($arguments);
            self::assertSame(2, $result['exit']);
            self::assertStringContainsString('Invalid or duplicate', $result['stderr']);
        }
    }

    public function testProductionMigrateRequiresForceAndRollbackIsRefused(): void
    {
        $productionEnvironment = $this->productionEnvironment();
        $withoutForce = $this->command(['migrate'], $productionEnvironment);
        $rollback = $this->command(['rollback'], $productionEnvironment);

        self::assertSame(1, $withoutForce['exit']);
        self::assertStringContainsString('requires --force', $withoutForce['stderr']);
        self::assertSame(1, $rollback['exit']);
        self::assertStringContainsString('disabled', $rollback['stderr']);
    }

    public function testProductionMigrateWithForceUsesOnlyTheIsolatedTestDatabase(): void
    {
        $this->writeMigrationFixture();
        $migrationCount = $this->migrationCount();

        $result = $this->command(['migrate', '--force'], $this->productionEnvironment());

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(
            sprintf('Applied migrations: %d', $migrationCount),
            $result['stdout'],
        );
        self::assertSame(1, (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 's0004_command_fixture'",
        )->fetchColumn());
    }

    public function testProductionRefusesEveryNonProductionSeederGroup(): void
    {
        foreach (['development', 'testing', 'demo'] as $group) {
            $result = $this->command(['seed', '--group=' . $group], $this->productionEnvironment());
            self::assertSame(1, $result['exit']);
            self::assertStringContainsString('refused', $result['stderr']);
        }
    }

    public function testConnectionFailuresDoNotDiscloseCredentialsOrDsn(): void
    {
        $environment = $this->testingEnvironment;
        $sentinel = 'S0_004_PRIVATE_SENTINEL';
        $environment['TEST_DB_PORT'] = '1';
        $environment['TEST_DB_PASSWORD'] = $sentinel;

        $result = $this->command(['status'], $environment);
        $output = $result['stdout'] . $result['stderr'];

        self::assertSame(1, $result['exit']);
        self::assertStringNotContainsString($sentinel, $output);
        self::assertStringNotContainsString('mysql:host=', $output);
        self::assertStringNotContainsString($environment['TEST_DB_USERNAME'], $output);
        self::assertStringContainsString('Database connection failed.', $result['stderr']);
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string>|null $environment
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function command(array $arguments, ?array $environment = null): array
    {
        $command = array_merge([
            PHP_BINARY,
            $this->basePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'database.php',
        ], $arguments);
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->basePath,
            $environment ?? $this->testingEnvironment,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /** @return array<string, string> */
    private function productionEnvironment(): array
    {
        $environment = $this->testingEnvironment;
        $environment['APP_ENV'] = 'production';

        foreach (['DRIVER', 'HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'CHARSET', 'COLLATION', 'TIMEZONE', 'CONNECT_TIMEOUT', 'SSL_CA'] as $suffix) {
            $environment['DB_' . $suffix] = $environment['TEST_DB_' . $suffix];
        }

        return $environment;
    }

    /** @return array<string, string> */
    private function childEnvironment(Environment $environment, string $prefix): array
    {
        $values = getenv();
        $child = is_array($values) ? array_map(static fn (mixed $value): string => (string) $value, $values) : [];

        foreach (['DRIVER', 'HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'CHARSET', 'COLLATION', 'TIMEZONE', 'CONNECT_TIMEOUT', 'SSL_CA'] as $suffix) {
            $key = $prefix . $suffix;
            $child[$key] = $environment->literalString($key, $suffix === 'DRIVER' ? 'mysql' : '');
        }

        $child['DB_DATABASE'] = $environment->literalString('DB_DATABASE');

        return $child;
    }

    private function writeMigrationFixture(): void
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'
            . DIRECTORY_SEPARATOR . '20990101000000_command_fixture.php';
        file_put_contents($path, <<<'PHP'
<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    public function up(\PDO $connection): void
    {
        $connection->exec('CREATE TABLE s0004_command_fixture (id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    }

    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE s0004_command_fixture');
    }
};
PHP);
        $this->fixtureFiles[] = $path;
    }

    private function writeSeederFixture(): void
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders'
            . DIRECTORY_SEPARATOR . 'command_test_fixture.php';
        file_put_contents($path, <<<'PHP'
<?php

declare(strict_types=1);

use App\Core\Seeder;

return new class extends Seeder {
    public function group(): string { return 'testing'; }
    public function run(\PDO $connection): void
    {
        $connection->exec("INSERT INTO s0004_command_seed (id, value_name) VALUES (1, 'test-only')");
    }
};
PHP);
        $this->fixtureFiles[] = $path;
    }

    private function migrationCount(): int
    {
        return count((new MigrationRunner(
            $this->connection,
            $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->discover());
    }

    private function cleanupDatabase(): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ([
                'notifications',
                'reviews',
                'review_invitations',
                'contact_messages',
                'pages',
                'settings',
                'gallery',
                'gallery_categories',
                'activities',
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
                's0004_command_seed',
                's0004_command_fixture',
                'schema_migrations',
            ] as $table) {
                $this->connection->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
            }
        } finally {
            $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
