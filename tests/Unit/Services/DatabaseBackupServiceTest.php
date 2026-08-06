<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseBackupFilenameGenerator;
use App\Services\DatabaseBackupManifest;
use App\Services\DatabaseBackupRoot;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseNativeClientCommandBuilder;
use App\Services\DatabaseNativeClientResolver;
use App\Services\NativeProcessRequest;
use App\Services\NativeProcessResult;
use App\Services\NativeProcessRunner;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseBackupServiceTest extends TestCase
{
    private const SQL_FILE = 'tara-guest-house-20260801T143000Z-abababababab.sql';
    private const MANIFEST_FILE = 'tara-guest-house-20260801T143000Z-abababababab.manifest.json';
    private const PASSWORD = 'fake-secret-password';

    private string $temporaryRoot;
    private string $storageRoot;
    private string $publicRoot;
    private string $backupRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-backup-service-' . bin2hex(random_bytes(6));
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

    public function testItCreatesAnAtomicBackupAndNonSecretManifestWithFakeNativeExecution(): void
    {
        $sql = "-- MariaDB dump 11.4\nCREATE TABLE rooms (id INT);\n";
        $runner = new BackupRunnerFake($sql);

        $result = $this->service($runner)->create();

        self::assertCount(2, $runner->requests);
        self::assertSame('mariadb-dump', $runner->requests[1]->executable);
        self::assertSame('--version', $runner->requests[0]->arguments[0]);
        self::assertStringStartsWith('--defaults-extra-file=', $runner->requests[1]->arguments[0]);
        self::assertSame(null, $runner->requests[1]->stdin);
        self::assertSame($this->backupRoot . DIRECTORY_SEPARATOR . self::SQL_FILE . '.part', $runner->requests[1]->stdout);
        self::assertSame('tgh_backup_test', $runner->requests[1]->arguments[array_key_last($runner->requests[1]->arguments)]);

        $joinedArguments = implode(' ', $runner->requests[1]->arguments);
        self::assertStringNotContainsString('fake-db.example', $joinedArguments);
        self::assertStringNotContainsString('fake-backup-user', $joinedArguments);
        self::assertStringNotContainsString(self::PASSWORD, $joinedArguments);

        $sqlPath = $this->backupRoot . DIRECTORY_SEPARATOR . self::SQL_FILE;
        $manifestPath = $this->backupRoot . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        self::assertFileExists($sqlPath);
        self::assertFileExists($manifestPath);
        self::assertSame($sql, file_get_contents($sqlPath));

        $manifestJson = file_get_contents($manifestPath);
        self::assertIsString($manifestJson);
        $manifest = DatabaseBackupManifest::fromJson($manifestJson);
        self::assertSame(self::SQL_FILE, $manifest->backupFile);
        self::assertSame(self::MANIFEST_FILE, $manifest->manifestFile());
        self::assertSame(strlen($sql), $manifest->bytes);
        self::assertSame(hash('sha256', $sql), $manifest->sha256);
        self::assertSame('mysql', $manifest->databaseEngine);
        self::assertSame('mariadb', $manifest->toolFamily);
        self::assertSame('MariaDB dump 11.4', $manifest->toolVersion);
        self::assertSame(32, $manifest->migrationCount);
        self::assertSame('20260805000300_expand_navigation_routes_for_publishing', $manifest->latestMigration);

        self::assertSame(self::SQL_FILE, $result->sqlBackupFile);
        self::assertSame(self::MANIFEST_FILE, $result->manifestFile);
        self::assertSame(strlen($sql), $result->bytes);
        self::assertSame(hash('sha256', $sql), $result->sha256);
        self::assertSame('2026-08-01T14:30:00Z', $result->createdAtUtc);
        self::assertSame('mariadb', $result->toolFamily);
        self::assertStringNotContainsString($this->temporaryRoot, $result->sqlBackupFile);
        self::assertStringNotContainsString($this->temporaryRoot, $result->manifestFile);

        self::assertStringNotContainsString(self::PASSWORD, $manifestJson);
        self::assertStringNotContainsString('fake-backup-user', $manifestJson);
        self::assertStringNotContainsString('fake-db.example', $manifestJson);
        self::assertStringNotContainsString($this->temporaryRoot, $manifestJson);
        $this->assertNoTemporaryFilesRemain();
    }

    #[DataProvider('invalidOutputProvider')]
    public function testItRejectsInvalidSqlOutputAndCleansEverything(string $output): void
    {
        $runner = new BackupRunnerFake($output);

        $this->expectSafeFailure(
            fn () => $this->service($runner)->create(),
            'Database backup output is invalid.',
        );

        $this->assertNoBackupFilesRemain();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidOutputProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => [" \t\r\n"];
        yield 'unrelated text' => ['This is not a logical SQL dump.'];
    }

    #[DataProvider('processFailureProvider')]
    public function testItCleansEverythingAfterNativeProcessFailure(string $behavior): void
    {
        $runner = new BackupRunnerFake("CREATE TABLE rooms (id INT);\n", $behavior);

        $this->expectSafeFailure(
            fn () => $this->service($runner)->create(),
            'Database backup could not be created.',
        );

        $this->assertNoBackupFilesRemain();
    }

    /** @return iterable<string, array{string}> */
    public static function processFailureProvider(): iterable
    {
        yield 'process start failure' => ['throw'];
        yield 'non-zero exit' => ['non-zero'];
    }

    #[DataProvider('existingFinalProvider')]
    public function testItDoesNotOverwriteAnExistingFinalFile(string $basename): void
    {
        $root = new DatabaseBackupRoot($this->storageRoot, $this->publicRoot);
        $path = $root->resolve($basename);
        file_put_contents($path, 'preserve-existing-file');
        $runner = new BackupRunnerFake("CREATE TABLE rooms (id INT);\n");

        $this->expectSafeFailure(
            fn () => $this->service($runner, $root)->create(),
            'Database backup could not be created.',
        );

        self::assertSame('preserve-existing-file', file_get_contents($path));
        self::assertCount(1, $runner->requests, 'Only executable resolution may occur before collision refusal.');
        $this->assertNoTemporaryFilesRemain();
    }

    /** @return iterable<string, array{string}> */
    public static function existingFinalProvider(): iterable
    {
        yield 'SQL backup' => [self::SQL_FILE];
        yield 'manifest' => [self::MANIFEST_FILE];
    }

    public function testInvalidRequiredMetadataFailsClosedAndLeavesNoBackupPair(): void
    {
        $runner = new BackupRunnerFake("CREATE TABLE rooms (id INT);\n");
        $root = new DatabaseBackupRoot($this->storageRoot, $this->publicRoot);
        $service = new DatabaseBackupService(
            $root,
            [
                'driver' => 'mysql',
                'host' => 'fake-db.example',
                'port' => 3306,
                'database' => 'tgh_backup_test',
                'username' => 'fake-backup-user',
                'password' => self::PASSWORD,
                'charset' => 'utf8mb4',
            ],
            new DatabaseBackupFilenameGenerator(static fn (int $length): string => str_repeat("\xAB", $length)),
            new DatabaseNativeClientResolver($runner),
            new DatabaseNativeClientCommandBuilder(),
            $runner,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01T20:15:00+05:45'),
            static fn (int $length): string => str_repeat("\xCD", $length),
            static fn (): array => [
                'application_revision' => null,
                'migration_count' => 0,
                'latest_migration' => null,
            ],
        );

        $this->expectSafeFailure(
            fn () => $service->create(),
            'Database backup could not be created.',
        );

        $this->assertNoBackupFilesRemain();
    }

    public function testMigrationMetadataChangingDuringDumpRejectsTheBackupPair(): void
    {
        $runner = new BackupRunnerFake("CREATE TABLE rooms (id INT);\n");
        $calls = 0;
        $metadata = static function () use (&$calls): array {
            $calls++;

            return [
                'application_revision' => null,
                'migration_count' => $calls === 1 ? 31 : 32,
                'latest_migration' => $calls === 1
                    ? '20260805000200_add_advanced_publishing_controls'
                    : '20260805000300_expand_navigation_routes_for_publishing',
            ];
        };

        $this->expectSafeFailure(
            fn () => $this->service($runner, null, null, $metadata)->create(),
            'Database backup could not be finalized.',
        );

        self::assertSame(2, $calls);
        $this->assertNoBackupFilesRemain();
    }

    public function testManifestWriteFailureLeavesNoOrphansOrCredentials(): void
    {
        $runner = new BackupRunnerFake("CREATE TABLE rooms (id INT);\n");
        $writer = static function (): void {
            throw new RuntimeException('writer detail that must not escape');
        };

        $this->expectSafeFailure(
            fn () => $this->service($runner, null, $writer)->create(),
            'Database backup could not be finalized.',
        );

        $this->assertNoBackupFilesRemain();
    }

    private function service(
        BackupRunnerFake $runner,
        ?DatabaseBackupRoot $root = null,
        ?callable $manifestWriter = null,
        ?callable $metadataProvider = null,
    ): DatabaseBackupService {
        $root ??= new DatabaseBackupRoot($this->storageRoot, $this->publicRoot);

        return new DatabaseBackupService(
            $root,
            [
                'driver' => 'mysql',
                'host' => 'fake-db.example',
                'port' => 3306,
                'database' => 'tgh_backup_test',
                'username' => 'fake-backup-user',
                'password' => self::PASSWORD,
                'charset' => 'utf8mb4',
            ],
            new DatabaseBackupFilenameGenerator(static fn (int $length): string => str_repeat("\xAB", $length)),
            new DatabaseNativeClientResolver($runner),
            new DatabaseNativeClientCommandBuilder(),
            $runner,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01T20:15:00+05:45'),
            static fn (int $length): string => str_repeat("\xCD", $length),
            $metadataProvider ?? static fn (): array => [
                'application_revision' => 'a87babe',
                'migration_count' => 32,
                'latest_migration' => '20260805000300_expand_navigation_routes_for_publishing',
            ],
            $manifestWriter,
        );
    }

    private function expectSafeFailure(callable $operation, string $expectedMessage): void
    {
        try {
            $operation();
            self::fail('Expected backup failure was not raised.');
        } catch (RuntimeException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
            self::assertStringNotContainsString('fake-backup-user', $exception->getMessage());
            self::assertStringNotContainsString('fake-db.example', $exception->getMessage());
            self::assertStringNotContainsString($this->temporaryRoot, $exception->getMessage());
        }
    }

    private function assertNoBackupFilesRemain(): void
    {
        self::assertFileDoesNotExist($this->backupRoot . DIRECTORY_SEPARATOR . self::SQL_FILE);
        self::assertFileDoesNotExist($this->backupRoot . DIRECTORY_SEPARATOR . self::MANIFEST_FILE);
        $this->assertNoTemporaryFilesRemain();
    }

    private function assertNoTemporaryFilesRemain(): void
    {
        $entries = is_dir($this->backupRoot) ? scandir($this->backupRoot) : [];
        self::assertIsArray($entries);
        $entries = array_values(array_diff($entries, ['.', '..', self::SQL_FILE, self::MANIFEST_FILE]));
        self::assertSame([], $entries, 'No .part or temporary client-option files may remain.');
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

final class BackupRunnerFake implements NativeProcessRunner
{
    /** @var list<NativeProcessRequest> */
    public array $requests = [];

    public function __construct(
        private readonly string $sqlOutput,
        private readonly string $behavior = 'success',
    ) {
    }

    public function run(NativeProcessRequest $request): NativeProcessResult
    {
        $this->requests[] = $request;

        if (count($this->requests) === 1) {
            self::assertVersionRequest($request);

            return new NativeProcessResult(0, "MariaDB dump 11.4\r\n");
        }

        if ($this->behavior === 'throw') {
            throw new RuntimeException('fake executable and path detail');
        }

        if ($request->stdout === null) {
            throw new RuntimeException('The backup request must select stdout.');
        }

        file_put_contents($request->stdout, $this->sqlOutput);

        return new NativeProcessResult($this->behavior === 'non-zero' ? 9 : 0, 'controlled fake stderr');
    }

    private static function assertVersionRequest(NativeProcessRequest $request): void
    {
        if ($request->arguments !== ['--version'] || $request->stdin !== null || $request->stdout !== null) {
            throw new RuntimeException('Unexpected resolver request.');
        }
    }
}
