<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Config;
use App\Core\ConnectionFactory;
use App\Core\Environment;
use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('database')]
final class AdministratorBootstrapCommandTest extends TestCase
{
    private string $basePath;

    private string $logPath;

    private PDO $connection;

    /** @var array<string, string> */
    private array $testingEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = dirname(__DIR__, 2);
        $this->logPath = $this->basePath . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'administrator-bootstrap-command-test.log';
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

        if (array_filter($testKeys, $environment->has(...)) === []) {
            self::markTestSkipped('An isolated test database is not configured.');
        }

        $configuration = Config::loadDatabase(
            $this->basePath . DIRECTORY_SEPARATOR . 'config',
            $environment,
        );
        self::assertMatchesRegularExpression('/_(?:test|testing)$/D', $configuration['database']);
        self::assertNotSame($environment->literalString('DB_DATABASE'), $configuration['database']);
        $this->connection = (new ConnectionFactory($configuration))->create();
        self::assertSame($configuration['database'], $this->connection->query('SELECT DATABASE()')->fetchColumn());
        $this->testingEnvironment = $this->childEnvironment($environment);
        $this->cleanupDatabase();
        $this->removeLog();

        (new MigrationRunner(
            $this->connection,
            $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        (new SeederRunner(
            $this->connection,
            $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders',
        ))->run('production', 'testing');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupDatabase();
        }

        $this->removeLog();
        parent::tearDown();
    }

    public function testHelpAndBootstrapHelpAreAvailableWithoutSecrets(): void
    {
        foreach ([['help'], ['bootstrap', '--help']] as $arguments) {
            $result = $this->command($arguments);
            self::assertSame(0, $result['exit']);
            self::assertStringContainsString('php bin/admin.php bootstrap', $result['stdout']);
            self::assertStringContainsString('--password-stdin', $result['stdout']);
            self::assertStringNotContainsString('--password ', $result['stdout']);
            self::assertSame('', $result['stderr']);
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidArguments(): iterable
    {
        yield 'missing command' => [[]];
        yield 'unknown command' => [['unknown']];
        yield 'unknown option' => [['bootstrap', '--unknown']];
        yield 'password option prohibited' => [['bootstrap', '--password=prohibited']];
        yield 'password confirmation option prohibited' => [['bootstrap', '--password-confirmation=prohibited']];
        yield 'duplicate flag' => [['bootstrap', '--force', '--force']];
        yield 'duplicate value option' => [['bootstrap', '--email=a@example.test', '--email=b@example.test']];
        yield 'missing option value' => [['bootstrap', '--email']];
        yield 'value on flag' => [['bootstrap', '--force=yes']];
        yield 'help combination' => [['bootstrap', '--help', '--force']];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsReturnUsageFailure(array $arguments): void
    {
        $result = $this->command($arguments);

        self::assertSame(2, $result['exit']);
        self::assertNotSame('', $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    public function testProtectedStdinRequiresAllIdentityOptions(): void
    {
        $result = $this->command(
            ['bootstrap', '--full-name=Bootstrap Operator', '--username=bootstrap.operator', '--password-stdin'],
            $this->secretInput(),
        );

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Identity options are required', $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    public function testInteractiveModeFailsClosedWhenProtectedTerminalInputIsUnavailable(): void
    {
        $arguments = array_values(array_filter(
            $this->bootstrapArguments(),
            static fn (string $argument): bool => $argument !== '--password-stdin',
        ));
        $result = $this->command($arguments);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Secure password input is unavailable', $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    /** @return iterable<string, array{string}> */
    public static function malformedProtectedInput(): iterable
    {
        yield 'empty' => [''];
        yield 'one line' => ["one-line-only\n"];
        yield 'extra line' => ["one\ntwo\nthree\n"];
        yield 'mismatch' => ["S1B-command-Test-Secret-9087\nS1B-command-Test-Secret-9088\n"];
    }

    #[DataProvider('malformedProtectedInput')]
    public function testMalformedOrMismatchedProtectedInputFailsBeforeConnection(string $input): void
    {
        $environment = $this->testingEnvironment;
        $environment['TEST_DB_PORT'] = '1';
        $result = $this->command($this->bootstrapArguments(), $input, $environment);

        self::assertSame(2, $result['exit']);
        self::assertStringNotContainsString('SQLSTATE', $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    public function testTestingBootstrapSucceedsOnceAndRepeatDoesNotChangeTheHash(): void
    {
        $secret = $this->secret();
        $created = $this->command($this->bootstrapArguments(), $secret . "\n" . $secret . "\n");

        self::assertSame(0, $created['exit']);
        self::assertSame("Administrator account created.\n", $created['stdout']);
        self::assertSame('', $created['stderr']);
        self::assertSame(1, $this->userCount());
        $hash = (string) $this->connection->query('SELECT password FROM users')->fetchColumn();
        self::assertNotSame($secret, $hash);
        self::assertTrue(password_verify($secret, $hash));

        $repeat = $this->command($this->bootstrapArguments(), $secret . "\n" . $secret . "\n");

        self::assertSame(1, $repeat['exit']);
        self::assertStringContainsString('already complete', $repeat['stderr']);
        self::assertSame($hash, $this->connection->query('SELECT password FROM users')->fetchColumn());
        $this->assertNoSensitiveOutputOrLog($created, $secret, $hash);
        $this->assertNoSensitiveOutputOrLog($repeat, $secret, $hash);
    }

    /** @return iterable<string, array{string}> */
    public static function forcedEnvironments(): iterable
    {
        yield 'staging' => ['staging'];
        yield 'production' => ['production'];
    }

    #[DataProvider('forcedEnvironments')]
    public function testProtectedEnvironmentsRequireForceBeforeConnection(string $environmentName): void
    {
        $environment = $this->environmentFor($environmentName);
        $environment['DB_PORT'] = '1';
        $result = $this->command($this->bootstrapArguments(), $this->secretInput(), $environment);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('requires --force', $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    #[DataProvider('forcedEnvironments')]
    public function testProtectedEnvironmentsAcceptForceButStillRejectInvalidInputBeforeConnecting(
        string $environmentName,
    ): void {
        $arguments = array_merge($this->bootstrapArguments(), ['--force']);
        $environment = $this->environmentFor($environmentName);
        $environment['DB_PORT'] = '1';
        $result = $this->command(
            $arguments,
            "S1B-command-Test-Secret-9087\nS1B-command-Test-Secret-9088\n",
            $environment,
        );

        self::assertSame(2, $result['exit']);
        self::assertStringNotContainsString('requires --force', $result['stderr']);
        self::assertStringNotContainsString('SQLSTATE', $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    public function testUnsupportedEnvironmentFailsBeforeDatabaseConfiguration(): void
    {
        $environment = $this->testingEnvironment;
        $environment['APP_ENV'] = 'preview';
        $environment['DB_PORT'] = '1';
        $environment['TEST_DB_PORT'] = '1';
        $result = $this->command($this->bootstrapArguments(), $this->secretInput(), $environment);

        self::assertSame(2, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "Administrator bootstrap is not allowed in this environment.\n",
            $result['stderr'],
        );
        self::assertSame(0, $this->userCount());
    }

    public function testRootDatabaseAccountIsRejectedWithoutDisclosure(): void
    {
        $environment = $this->testingEnvironment;
        $environment['TEST_DB_USERNAME'] = 'RoOt';
        $result = $this->command($this->bootstrapArguments(), $this->secretInput(), $environment);

        self::assertSame(1, $result['exit']);
        self::assertSame("Administrator bootstrap failed.\n", $result['stderr']);
        self::assertStringNotContainsString('RoOt', $result['stdout'] . $result['stderr']);
        self::assertSame(0, $this->userCount());
    }

    public function testCommandSourceContainsCliAndPlatformProtectedInputGuards(): void
    {
        $source = file_get_contents($this->basePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'admin.php');

        self::assertIsString($source);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $source);
        self::assertStringContainsString("PHP_OS_FAMILY === 'Windows'", $source);
        self::assertStringContainsString('Read-Host', $source);
        self::assertStringContainsString('-AsSecureString', $source);
        self::assertStringContainsString("exec('stty -echo'", $source);
    }

    /** @return list<string> */
    private function bootstrapArguments(): array
    {
        return [
            'bootstrap',
            '--full-name=Bootstrap Operator',
            '--username=bootstrap.operator',
            '--email=bootstrap.operator@example.test',
            '--phone=+977 15550101',
            '--password-stdin',
        ];
    }

    private function secret(): string
    {
        return implode('', ['S1B-command-', 'Test-Secret-9087']);
    }

    private function secretInput(): string
    {
        return $this->secret() . "\n" . $this->secret() . "\n";
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string>|null $environment
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function command(
        array $arguments,
        string $input = '',
        ?array $environment = null,
    ): array {
        $command = array_merge([
            PHP_BINARY,
            $this->basePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'admin.php',
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
        fwrite($pipes[0], $input);
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
    private function childEnvironment(Environment $environment): array
    {
        $values = getenv();
        $child = is_array($values)
            ? array_map(static fn (mixed $value): string => (string) $value, $values)
            : [];

        foreach (['DRIVER', 'HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'CHARSET', 'COLLATION', 'TIMEZONE', 'CONNECT_TIMEOUT', 'SSL_CA'] as $suffix) {
            $key = 'TEST_DB_' . $suffix;
            $child[$key] = $environment->literalString($key, $suffix === 'DRIVER' ? 'mysql' : '');
        }

        $child['DB_DATABASE'] = $environment->literalString('DB_DATABASE');
        $child['APP_ENV'] = 'testing';
        $child['APP_NAME'] = 'Tara Guest House Test';
        $child['APP_DEBUG'] = 'false';
        $child['APP_URL'] = 'http://localhost';
        $child['APP_TIMEZONE'] = 'Asia/Kathmandu';
        $child['SESSION_NAME'] = 'tgh_test_session';
        $child['SESSION_INACTIVITY_TIMEOUT'] = '1800';
        $child['SESSION_ABSOLUTE_LIFETIME'] = '43200';
        $child['SESSION_SECURE_COOKIE'] = 'false';
        $child['SESSION_HTTP_ONLY'] = 'true';
        $child['SESSION_SAME_SITE'] = 'Lax';
        $child['SESSION_PATH'] = '/';
        $child['SESSION_DOMAIN'] = '';
        $child['LOG_CHANNEL'] = 'file';
        $child['LOG_LEVEL'] = 'debug';
        $child['LOG_PATH'] = 'storage/logs/administrator-bootstrap-command-test.log';

        return $child;
    }

    /** @return array<string, string> */
    private function environmentFor(string $environmentName): array
    {
        $environment = $this->testingEnvironment;
        $environment['APP_ENV'] = $environmentName;
        $environment['APP_URL'] = 'https://example.test';
        $environment['SESSION_SECURE_COOKIE'] = 'true';

        foreach (['DRIVER', 'HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'CHARSET', 'COLLATION', 'TIMEZONE', 'CONNECT_TIMEOUT', 'SSL_CA'] as $suffix) {
            $environment['DB_' . $suffix] = $environment['TEST_DB_' . $suffix];
        }

        return $environment;
    }

    /** @param array{exit: int, stdout: string, stderr: string} $result */
    private function assertNoSensitiveOutputOrLog(array $result, string $secret, string $hash): void
    {
        $log = is_file($this->logPath) ? file_get_contents($this->logPath) : '';
        self::assertIsString($log);
        $combined = $result['stdout'] . $result['stderr'] . $log;

        foreach ([
            $secret,
            $hash,
            'Bootstrap Operator',
            'bootstrap.operator',
            'bootstrap.operator@example.test',
            '+977 15550101',
        ] as $sensitiveValue) {
            self::assertStringNotContainsString($sensitiveValue, $combined);
        }
    }

    private function userCount(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function cleanupDatabase(): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ([
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
            ] as $table) {
                $this->connection->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
            }
        } finally {
            $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function removeLog(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }
}
