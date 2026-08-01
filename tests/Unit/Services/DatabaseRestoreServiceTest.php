<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\DatabaseRestorePreflightRepository;
use App\Services\DatabaseBackupManifest;
use App\Services\DatabaseBackupRoot;
use App\Services\DatabaseNativeClientCommandBuilder;
use App\Services\DatabaseNativeClientResolver;
use App\Services\DatabaseRestoreService;
use App\Services\NativeProcessRequest;
use App\Services\NativeProcessResult;
use App\Services\NativeProcessRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseRestoreServiceTest extends TestCase
{
    private const SQL_FILE = 'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.sql';
    private const MANIFEST_FILE = 'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.manifest.json';
    private const DATABASE = 'fake_restore_target';
    private const PASSWORD = 'fake-restore-password';
    private const USERNAME = 'fake-restore-user';
    private const HOST = 'fake-restore-host';
    private const SQL = "-- MariaDB dump 11.4\nCREATE TABLE rooms (id INT);\n";

    private string $temporaryRoot;
    private string $storageRoot;
    private string $publicRoot;
    private string $backupRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-restore-preflight-' . bin2hex(random_bytes(6));
        $this->storageRoot = $this->temporaryRoot . DIRECTORY_SEPARATOR . 'storage';
        $this->publicRoot = $this->temporaryRoot . DIRECTORY_SEPARATOR . 'public';
        mkdir($this->temporaryRoot);
        mkdir($this->storageRoot);
        mkdir($this->publicRoot);
        $this->backupRoot = $this->storageRoot . DIRECTORY_SEPARATOR . 'backups';
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->temporaryRoot);
    }

    public function testValidBackupAndEmptyConfiguredTargetPassPreflightWithoutRestoreExecution(): void
    {
        [$sqlPath, $manifestPath] = $this->writeValidBackup();
        $sqlBefore = file_get_contents($sqlPath);
        $manifestBefore = file_get_contents($manifestPath);
        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([0], "mysql\0\r\n8.0.44");

        $result = $this->service($repository, $runner)->preflight(self::SQL_FILE);

        self::assertSame([self::DATABASE], $repository->databaseNames);
        self::assertSame(['mariadb'], $runner->executables());
        $runner->assertVersionOnlyRequests();
        self::assertSame(self::SQL_FILE, $result->sqlBackupFile);
        self::assertSame(self::MANIFEST_FILE, $result->manifestFile);
        self::assertSame(strlen(self::SQL), $result->bytes);
        self::assertSame(hash('sha256', self::SQL), $result->sha256);
        self::assertSame('2026-08-01T14:30:00Z', $result->backupCreatedAtUtc);
        self::assertSame('mariadb', $result->backupToolFamily);
        self::assertSame('MariaDB dump 11.4', $result->backupToolVersion);
        self::assertSame('mariadb', $result->restoreToolFamily);
        self::assertSame('mysql   8.0.44', $result->restoreToolVersion);
        self::assertSame('mysql', $result->databaseEngine);
        self::assertSame(0, $result->targetTableCount);
        self::assertSame(0, $result->targetViewCount);
        self::assertFalse($result->restoreExecuted);
        self::assertStringNotContainsString($this->temporaryRoot, $result->sqlBackupFile);
        self::assertStringNotContainsString(self::DATABASE, implode('|', get_object_vars($result)));
        self::assertSame($sqlBefore, file_get_contents($sqlPath));
        self::assertSame($manifestBefore, file_get_contents($manifestPath));
        self::assertSame([], glob($this->backupRoot . DIRECTORY_SEPARATOR . '.db-client-*.cnf') ?: []);
    }

    #[DataProvider('invalidBasenameProvider')]
    public function testUnsafeBackupBasenamesFailBeforeDatabaseOrNativeInspection(string $basename): void
    {
        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([0]);

        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner)->preflight($basename),
            'Database restore preflight failed.',
        );

        self::assertSame([], $repository->databaseNames);
        self::assertSame([], $runner->executables());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBasenameProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'dot dot' => ['..'];
        yield 'traversal' => ['../' . self::SQL_FILE];
        yield 'nested forward slash' => ['nested/' . self::SQL_FILE];
        yield 'nested backslash' => ['nested\\' . self::SQL_FILE];
        yield 'absolute Unix' => ['/absolute.sql'];
        yield 'Windows drive' => ['C:\\absolute.sql'];
        yield 'UNC' => ['\\\\server\\share\\backup.sql'];
        yield 'unrelated SQL' => ['unrelated.sql'];
        yield 'part file' => [self::SQL_FILE . '.part'];
        yield 'manifest input' => [self::MANIFEST_FILE];
        yield 'URL' => ['https://example.test/backup.sql'];
    }

    #[DataProvider('invalidSqlProvider')]
    public function testInvalidSqlFilesFailBeforeDatabaseOrNativeInspection(string $mode): void
    {
        $root = new DatabaseBackupRoot($this->storageRoot, $this->publicRoot);
        $sqlPath = $root->resolve(self::SQL_FILE);

        if ($mode === 'directory') {
            mkdir($sqlPath);
        } elseif ($mode !== 'missing') {
            file_put_contents($sqlPath, match ($mode) {
                'empty' => '',
                'whitespace' => " \t\r\n",
                default => 'unrelated output',
            });
        }

        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([0]);
        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner, $root)->preflight(self::SQL_FILE),
            'Database restore preflight failed.',
        );

        self::assertSame([], $repository->databaseNames);
        self::assertSame([], $runner->executables());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSqlProvider(): iterable
    {
        yield 'missing SQL' => ['missing'];
        yield 'directory SQL' => ['directory'];
        yield 'empty SQL' => ['empty'];
        yield 'whitespace SQL' => ['whitespace'];
        yield 'unrecognized SQL' => ['unrecognized'];
    }

    #[DataProvider('invalidManifestProvider')]
    public function testInvalidManifestOrIntegrityFailsBeforeDatabaseOrNativeInspection(string $mode): void
    {
        [$sqlPath, $manifestPath] = $this->writeValidBackup();
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        if ($mode === 'missing') {
            unlink($manifestPath);
        } elseif ($mode === 'malformed') {
            file_put_contents($manifestPath, '{bad json');
        } elseif ($mode === 'filename') {
            $manifest['backup_file'] = 'tara-guest-house-20260801T143000Z-ffffffffffff.sql';
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } elseif ($mode === 'byte count') {
            $manifest['bytes']++;
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } elseif ($mode === 'checksum') {
            $manifest['sha256'] = str_repeat('f', 64);
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $key = match ($mode) {
                'version' => 'format_version',
                'application' => 'application',
                default => 'backup_format',
            };
            $manifest[$key] = match ($mode) {
                'version' => 2,
                'application' => 'another-application',
                default => 'physical',
            };
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([0]);
        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner)->preflight(self::SQL_FILE),
            'Database restore preflight failed.',
        );

        self::assertSame([], $repository->databaseNames);
        self::assertSame([], $runner->executables());
        self::assertFileExists($sqlPath);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidManifestProvider(): iterable
    {
        yield 'missing manifest' => ['missing'];
        yield 'malformed JSON' => ['malformed'];
        yield 'unsupported version' => ['version'];
        yield 'wrong application' => ['application'];
        yield 'unsupported format' => ['format'];
        yield 'backup filename mismatch' => ['filename'];
        yield 'byte-count mismatch' => ['byte count'];
        yield 'checksum mismatch' => ['checksum'];
    }

    #[DataProvider('targetFailureProvider')]
    public function testTargetInspectionFailuresAreSafeAndNeverResolveARestoreClient(
        bool $exists,
        int $tables,
        int $views,
        bool $throws,
        string $expectedMessage,
    ): void {
        $this->writeValidBackup();
        $repository = new RestorePreflightRepositoryFake($exists, $tables, $views, $throws);
        $runner = new RestorePreflightRunnerFake([0]);

        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner)->preflight(self::SQL_FILE),
            $expectedMessage,
        );

        self::assertSame([self::DATABASE], $repository->databaseNames);
        self::assertSame([], $runner->executables());
    }

    /** @return iterable<string, array{bool, int, int, bool, string}> */
    public static function targetFailureProvider(): iterable
    {
        yield 'database missing' => [false, 0, 0, false, 'Database restore preflight failed.'];
        yield 'one base table' => [true, 1, 0, false, 'The configured restore database is not empty.'];
        yield 'one view' => [true, 0, 1, false, 'The configured restore database is not empty.'];
        yield 'metadata query failure' => [true, 0, 0, true, 'Database restore preflight failed.'];
    }

    public function testUnavailableRestoreClientIsSafeAndDoesNotCreateAnImportRequest(): void
    {
        $this->writeValidBackup();
        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([1, 1, 1, 1]);

        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner)->preflight(self::SQL_FILE),
            'Database restore client is unavailable.',
        );

        self::assertSame(['mariadb', 'mariadb.exe', 'mysql', 'mysql.exe'], $runner->executables());
        $runner->assertVersionOnlyRequests();
        self::assertSame([], glob($this->backupRoot . DIRECTORY_SEPARATOR . '.db-client-*.cnf') ?: []);
    }

    public function testExecuteRerunsPreflightAndUsesPrivateStdinWithoutChangingTheBackup(): void
    {
        [$sqlPath, $manifestPath] = $this->writeValidBackup();
        $sqlBefore = file_get_contents($sqlPath);
        $manifestBefore = file_get_contents($manifestPath);
        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([0, 0, 0], 'mysql 8.0.44');
        $service = $this->service($repository, $runner);

        $preflight = $service->preflight(self::SQL_FILE);
        $result = $service->execute(self::SQL_FILE);

        self::assertFalse($preflight->restoreExecuted);
        self::assertSame([self::DATABASE, self::DATABASE], $repository->databaseNames);
        self::assertSame(['mariadb', 'mariadb', 'mariadb'], $runner->executables());
        $requests = $runner->requests();
        self::assertSame(['--version'], $requests[0]->arguments);
        self::assertSame(['--version'], $requests[1]->arguments);
        $restoreRequest = $requests[2];
        self::assertStringStartsWith('--defaults-extra-file=', $restoreRequest->arguments[0]);
        $optionPath = substr($restoreRequest->arguments[0], strlen('--defaults-extra-file='));
        self::assertTrue($runner->optionFileExistedDuringRestore);
        self::assertFileDoesNotExist($optionPath);
        self::assertSame('--default-character-set=utf8mb4', $restoreRequest->arguments[1]);
        self::assertSame(self::DATABASE, $restoreRequest->arguments[2]);
        self::assertSame($sqlPath, $restoreRequest->stdin);
        self::assertNull($restoreRequest->stdout);
        $joinedArguments = implode(' ', $restoreRequest->arguments);
        foreach ([self::PASSWORD, self::USERNAME, self::HOST, '3306', $sqlPath, '<', '--force'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $joinedArguments);
        }
        self::assertDoesNotMatchRegularExpression('/\b(?:DROP|CREATE|TRUNCATE|ALTER|GRANT)\b/i', $joinedArguments);
        self::assertSame(self::SQL_FILE, $result->sqlBackupFile);
        self::assertSame(self::MANIFEST_FILE, $result->manifestFile);
        self::assertSame(strlen(self::SQL), $result->bytes);
        self::assertSame(hash('sha256', self::SQL), $result->sha256);
        self::assertSame('2026-08-01T15:45:00Z', $result->restoredAtUtc);
        self::assertSame('mariadb', $result->toolFamily);
        self::assertSame('mysql 8.0.44', $result->toolVersion);
        self::assertTrue($result->restoreExecuted);
        self::assertStringNotContainsString($this->temporaryRoot, $result->sqlBackupFile);
        self::assertSame($sqlBefore, file_get_contents($sqlPath));
        self::assertSame($manifestBefore, file_get_contents($manifestPath));
        self::assertFileExists($sqlPath);
        self::assertFileExists($manifestPath);
    }

    #[DataProvider('restoreProcessFailureProvider')]
    public function testRestoreProcessFailureIsSanitizedAndCleansOptionFile(int|\Throwable $outcome): void
    {
        [$sqlPath, $manifestPath] = $this->writeValidBackup();
        $sqlBefore = file_get_contents($sqlPath);
        $manifestBefore = file_get_contents($manifestPath);
        $repository = new RestorePreflightRepositoryFake();
        $runner = new RestorePreflightRunnerFake([0, $outcome], 'raw native stderr with secrets');

        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner)->execute(self::SQL_FILE),
            'Database restore could not be completed.',
        );

        self::assertSame([self::DATABASE], $repository->databaseNames);
        self::assertCount(2, $runner->requests());
        self::assertTrue($runner->optionFileExistedDuringRestore);
        self::assertSame([], glob($this->backupRoot . DIRECTORY_SEPARATOR . '.db-client-*.cnf') ?: []);
        self::assertSame($sqlBefore, file_get_contents($sqlPath));
        self::assertSame($manifestBefore, file_get_contents($manifestPath));
    }

    /** @return iterable<string, array{int|\Throwable}> */
    public static function restoreProcessFailureProvider(): iterable
    {
        yield 'non-zero exit' => [7];
        yield 'process-start failure' => [new RuntimeException('native path and stderr detail')];
    }

    #[DataProvider('executePreflightFailureProvider')]
    public function testExecutePreflightFailurePreventsOptionFileAndRestoreRequest(
        bool $exists,
        int $tables,
        int $views,
        string $expectedMessage,
    ): void
    {
        $this->writeValidBackup();
        $repository = new RestorePreflightRepositoryFake($exists, $tables, $views);
        $runner = new RestorePreflightRunnerFake([0]);

        $this->expectSafeFailure(
            fn () => $this->service($repository, $runner)->execute(self::SQL_FILE),
            $expectedMessage,
        );

        self::assertSame([self::DATABASE], $repository->databaseNames);
        self::assertSame([], $runner->requests());
        self::assertSame([], glob($this->backupRoot . DIRECTORY_SEPARATOR . '.db-client-*.cnf') ?: []);
    }

    /** @return iterable<string, array{bool, int, int, string}> */
    public static function executePreflightFailureProvider(): iterable
    {
        yield 'configured database missing' => [false, 0, 0, 'Database restore preflight failed.'];
        yield 'base table present' => [true, 1, 0, 'The configured restore database is not empty.'];
        yield 'view present' => [true, 0, 1, 'The configured restore database is not empty.'];
    }

    /** @return array{string, string} */
    private function writeValidBackup(): array
    {
        $root = new DatabaseBackupRoot($this->storageRoot, $this->publicRoot);
        $sqlPath = $root->resolve(self::SQL_FILE);
        $manifestPath = $root->resolve(self::MANIFEST_FILE);
        file_put_contents($sqlPath, self::SQL);
        $manifest = new DatabaseBackupManifest(
            '2026-08-01T14:30:00Z',
            self::SQL_FILE,
            hash('sha256', self::SQL),
            strlen(self::SQL),
            'mysql',
            'mariadb',
            'MariaDB dump 11.4',
        );
        file_put_contents($manifestPath, $manifest->toJson());

        return [$sqlPath, $manifestPath];
    }

    private function service(
        RestorePreflightRepositoryFake $repository,
        RestorePreflightRunnerFake $runner,
        ?DatabaseBackupRoot $root = null,
    ): DatabaseRestoreService {
        return new DatabaseRestoreService(
            $root ?? new DatabaseBackupRoot($this->storageRoot, $this->publicRoot),
            [
                'driver' => 'mysql',
                'database' => self::DATABASE,
                'host' => self::HOST,
                'port' => 3306,
                'username' => self::USERNAME,
                'password' => self::PASSWORD,
                'charset' => 'utf8mb4',
            ],
            $repository,
            new DatabaseNativeClientResolver($runner),
            new DatabaseNativeClientCommandBuilder(),
            $runner,
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-08-01T21:30:00+05:45'),
            static fn (int $length): string => str_repeat("\xEF", $length),
        );
    }

    private function expectSafeFailure(callable $operation, string $expectedMessage): void
    {
        try {
            $operation();
            self::fail('Expected restore preflight failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertNull($exception->getPrevious());
            foreach ([self::PASSWORD, self::USERNAME, self::HOST, self::DATABASE, $this->temporaryRoot, 'SQLSTATE'] as $secret) {
                self::assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }

    private function removeTree(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}

final class RestorePreflightRepositoryFake implements DatabaseRestorePreflightRepository
{
    /** @var list<string> */
    public array $databaseNames = [];

    public function __construct(
        private readonly bool $exists = true,
        private readonly int $tables = 0,
        private readonly int $views = 0,
        private readonly bool $throws = false,
    ) {
    }

    public function inspectTarget(string $databaseName): array
    {
        $this->databaseNames[] = $databaseName;
        if ($this->throws) {
            throw new RuntimeException('query, DSN, and path details');
        }

        return ['exists' => $this->exists, 'table_count' => $this->tables, 'view_count' => $this->views];
    }
}

final class RestorePreflightRunnerFake implements NativeProcessRunner
{
    /** @var list<NativeProcessRequest> */
    private array $requests = [];

    public bool $optionFileExistedDuringRestore = false;

    /** @param list<int|\Throwable> $exitCodes */
    public function __construct(
        private array $exitCodes,
        private readonly string $stderr = '',
    ) {
    }

    public function run(NativeProcessRequest $request): NativeProcessResult
    {
        $this->requests[] = $request;
        $exitCode = array_shift($this->exitCodes);
        if ($exitCode instanceof \Throwable) {
            $this->recordOptionFileState($request);
            throw $exitCode;
        }
        if (!is_int($exitCode)) {
            throw new RuntimeException('Missing fake exit code.');
        }

        $this->recordOptionFileState($request);

        return new NativeProcessResult($exitCode, $this->stderr);
    }

    /** @return list<string> */
    public function executables(): array
    {
        return array_map(static fn (NativeProcessRequest $request): string => $request->executable, $this->requests);
    }

    public function assertVersionOnlyRequests(): void
    {
        foreach ($this->requests as $request) {
            TestCase::assertSame(['--version'], $request->arguments);
            TestCase::assertNull($request->stdin);
            TestCase::assertNull($request->stdout);
        }
    }

    /** @return list<NativeProcessRequest> */
    public function requests(): array
    {
        return $this->requests;
    }

    private function recordOptionFileState(NativeProcessRequest $request): void
    {
        if ($request->arguments === [] || !str_starts_with($request->arguments[0], '--defaults-extra-file=')) {
            return;
        }

        $path = substr($request->arguments[0], strlen('--defaults-extra-file='));
        $this->optionFileExistedDuringRestore = is_file($path);
    }
}
