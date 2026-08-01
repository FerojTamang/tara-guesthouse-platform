<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use App\Cli\RestoreDatabaseCommand;
use App\Services\DatabaseRestoreOperations;
use App\Services\DatabaseRestorePreflightResult;
use App\Services\DatabaseRestoreResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RestoreDatabaseCommandTest extends TestCase
{
    private const SQL_FILE = 'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.sql';
    private const MANIFEST_FILE = 'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.manifest.json';
    private const DATABASE = 'fake_restore_target';
    private const PASSWORD = 'fake-restore-password';
    private const ROOT = 'C:\\private\\storage\\backups';

    public function testHelpIsCompleteAndLoadsNeitherConfigurationNorService(): void
    {
        $configurationCalled = false;
        $serviceCalled = false;
        $command = new RestoreDatabaseCommand(
            static function () use (&$configurationCalled): never {
                $configurationCalled = true;
                throw new RuntimeException();
            },
            static function () use (&$serviceCalled): never {
                $serviceCalled = true;
                throw new RuntimeException();
            },
        );

        $result = $command->execute(['--help']);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertFalse($configurationCalled);
        self::assertFalse($serviceCalled);
        foreach ([
            'Restore defaults to preflight',
            'manifest, byte count',
            'SHA-256',
            'configured application database',
            'zero tables and zero views',
            '--execute',
            'exact database confirmation',
            'exact environment confirmation',
            'through stdin',
            'no force mode',
            'DROP DATABASE',
            'CREATE DATABASE',
            'TRUNCATE',
            'backup and manifest are retained',
            'disposable, empty test database',
        ] as $text) {
            self::assertStringContainsStringIgnoringCase($text, $result['stdout']);
        }
        self::assertStringNotContainsString(self::DATABASE, $result['stdout']);
    }

    #[DataProvider('invalidSyntaxProvider')]
    public function testInvalidSyntaxFailsBeforeConfigurationAndService(array $arguments): void
    {
        $configurationCalls = 0;
        $serviceCalls = 0;
        $command = new RestoreDatabaseCommand(
            static function () use (&$configurationCalls): array {
                $configurationCalls++;
                return ['database' => self::DATABASE, 'environment' => 'local'];
            },
            static function () use (&$serviceCalls): never {
                $serviceCalls++;
                throw new RuntimeException();
            },
        );

        $result = $command->execute($arguments);

        self::assertSame(2, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertNotSame('', $result['stderr']);
        self::assertSame(0, $configurationCalls);
        self::assertSame(0, $serviceCalls);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidSyntaxProvider(): iterable
    {
        yield 'missing backup' => [[]];
        yield 'unknown option' => [[self::SQL_FILE, '--unknown']];
        yield 'duplicate execute' => [[self::SQL_FILE, '--execute', '--execute']];
        yield 'duplicate confirmation' => [[self::SQL_FILE, '--execute', '--confirm-database=x', '--confirm-database=x']];
        yield 'malformed confirmation' => [[self::SQL_FILE, '--execute', '--confirm-database']];
        yield 'empty confirmation' => [[self::SQL_FILE, '--execute', '--confirm-database=']];
        yield 'confirmation without execute' => [[self::SQL_FILE, '--confirm-database=x']];
        yield 'force prohibited' => [[self::SQL_FILE, '--execute', '--confirm-database=x', '--force']];
        yield 'arbitrary relative path' => [['../' . self::SQL_FILE]];
        yield 'absolute path' => [['C:\\private\\backup.sql']];
    }

    public function testExecuteWithoutDatabaseConfirmationFailsBeforeConfigurationAndService(): void
    {
        $configurationCalled = false;
        $serviceCalled = false;
        $command = new RestoreDatabaseCommand(
            static function () use (&$configurationCalled): never {
                $configurationCalled = true;
                throw new RuntimeException();
            },
            static function () use (&$serviceCalled): never {
                $serviceCalled = true;
                throw new RuntimeException();
            },
        );

        $result = $command->execute([self::SQL_FILE, '--execute']);

        self::assertSame(2, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame("Restore execution confirmation is invalid.\n", $result['stderr']);
        self::assertFalse($configurationCalled);
        self::assertFalse($serviceCalled);
    }

    public function testBasenameOnlyRunsPreflightAndPrintsBoundedMetadata(): void
    {
        $service = new RestoreOperationsFake(self::preflightResult(), self::restoreResult());
        $configurationCalls = 0;
        $command = $this->command($service, 'local', $configurationCalls);

        $result = $command->execute([self::SQL_FILE]);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertSame([self::SQL_FILE], $service->preflightCalls);
        self::assertSame([], $service->executeCalls);
        self::assertSame(0, $configurationCalls);
        foreach ([
            self::SQL_FILE,
            self::MANIFEST_FILE,
            'Bytes: 123',
            'SHA-256: ' . str_repeat('a', 64),
            'Target tables: 0',
            'Target views: 0',
            'Restore not executed.',
        ] as $metadata) {
            self::assertStringContainsString($metadata, $result['stdout']);
        }
        $this->assertNonSecret($result['stdout']);
    }

    public function testPreflightOperationalFailureIsSanitized(): void
    {
        $service = new RestoreOperationsFake(
            self::preflightResult(),
            self::restoreResult(),
            new RuntimeException(
                'Database restore preflight failed.',
                previous: new RuntimeException(self::PASSWORD . ' raw stderr ' . self::ROOT),
            ),
        );
        $configurationCalls = 0;

        $result = $this->command($service, 'local', $configurationCalls)->execute([self::SQL_FILE]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame("Database restore preflight failed.\n", $result['stderr']);
        self::assertStringNotContainsString('Stack trace', $result['stderr']);
        $this->assertNonSecret($result['stderr']);
    }

    #[DataProvider('invalidConfirmationProvider')]
    public function testInvalidExecutionConfirmationFailsBeforeServiceConstruction(
        string $environment,
        array $arguments,
    ): void {
        $serviceCalls = 0;
        $configurationCalls = 0;
        $command = new RestoreDatabaseCommand(
            static function () use ($environment, &$configurationCalls): array {
                $configurationCalls++;
                return ['database' => self::DATABASE, 'environment' => $environment];
            },
            static function () use (&$serviceCalls): never {
                $serviceCalls++;
                throw new RuntimeException();
            },
        );

        $result = $command->execute($arguments);

        self::assertSame(2, $result['exit']);
        self::assertSame("Restore execution confirmation is invalid.\n", $result['stderr']);
        self::assertSame(1, $configurationCalls);
        self::assertSame(0, $serviceCalls);
        self::assertStringNotContainsString(self::DATABASE, $result['stderr']);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function invalidConfirmationProvider(): iterable
    {
        yield 'wrong database' => ['local', [self::SQL_FILE, '--execute', '--confirm-database=wrong']];
        yield 'partial database' => ['local', [self::SQL_FILE, '--execute', '--confirm-database=fake_restore']];
        yield 'staging environment missing' => ['staging', [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE]];
        yield 'staging environment wrong' => [
            'staging',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE, '--confirm-environment=production'],
        ];
        yield 'production environment case mismatch' => [
            'production',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE, '--confirm-environment=Production'],
        ];
        yield 'local rejects unnecessary environment confirmation' => [
            'local',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE, '--confirm-environment=local'],
        ];
    }

    #[DataProvider('validExecutionProvider')]
    public function testExactConfirmationExecutesAndPrintsOnlyBoundedResult(
        string $environment,
        array $arguments,
    ): void {
        $service = new RestoreOperationsFake(self::preflightResult(), self::restoreResult());
        $configurationCalls = 0;

        $result = $this->command($service, $environment, $configurationCalls)->execute($arguments);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertSame([], $service->preflightCalls);
        self::assertSame([self::SQL_FILE], $service->executeCalls);
        self::assertSame(1, $configurationCalls);
        self::assertStringContainsString('Restore executed.', $result['stdout']);
        self::assertStringContainsString('Restored UTC: 2026-08-01T15:45:00Z', $result['stdout']);
        $this->assertNonSecret($result['stdout']);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function validExecutionProvider(): iterable
    {
        yield 'local exact database' => [
            'local',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE],
        ];
        yield 'testing exact database' => [
            'testing',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE],
        ];
        yield 'staging exact database and environment' => [
            'staging',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE, '--confirm-environment=staging'],
        ];
        yield 'production exact database and environment' => [
            'production',
            [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE, '--confirm-environment=production'],
        ];
    }

    public function testExecutionFailureIsSanitized(): void
    {
        $service = new RestoreOperationsFake(
            self::preflightResult(),
            self::restoreResult(),
            null,
            new RuntimeException(
                'Database restore could not be completed.',
                previous: new RuntimeException(self::PASSWORD . ' raw native stderr ' . self::ROOT),
            ),
        );
        $configurationCalls = 0;
        $arguments = [self::SQL_FILE, '--execute', '--confirm-database=' . self::DATABASE];

        $result = $this->command($service, 'local', $configurationCalls)->execute($arguments);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame("Database restore could not be completed.\n", $result['stderr']);
        self::assertStringNotContainsString('raw native stderr', $result['stderr']);
        $this->assertNonSecret($result['stderr']);
    }

    private function command(
        RestoreOperationsFake $service,
        string $environment,
        int &$configurationCalls,
    ): RestoreDatabaseCommand {
        return new RestoreDatabaseCommand(
            static function () use ($environment, &$configurationCalls): array {
                $configurationCalls++;
                return ['database' => self::DATABASE, 'environment' => $environment];
            },
            static fn (): RestoreOperationsFake => $service,
        );
    }

    private static function preflightResult(): DatabaseRestorePreflightResult
    {
        return new DatabaseRestorePreflightResult(
            self::SQL_FILE,
            self::MANIFEST_FILE,
            123,
            str_repeat('a', 64),
            '2026-08-01T14:30:00Z',
            'mariadb',
            'MariaDB dump 11.4',
            'mysql',
            'mysql 8.0.44',
            'mysql',
            0,
            0,
            false,
        );
    }

    private static function restoreResult(): DatabaseRestoreResult
    {
        return new DatabaseRestoreResult(
            self::SQL_FILE,
            self::MANIFEST_FILE,
            123,
            str_repeat('a', 64),
            '2026-08-01T15:45:00Z',
            'mysql',
            'mysql 8.0.44',
            true,
        );
    }

    private function assertNonSecret(string $output): void
    {
        foreach ([self::DATABASE, self::PASSWORD, self::ROOT, 'fake-restore-user', 'fake-restore-host', 'SQLSTATE'] as $secret) {
            self::assertStringNotContainsString($secret, $output);
        }
    }
}

final class RestoreOperationsFake implements DatabaseRestoreOperations
{
    /** @var list<string> */
    public array $preflightCalls = [];

    /** @var list<string> */
    public array $executeCalls = [];

    public function __construct(
        private readonly DatabaseRestorePreflightResult $preflightResult,
        private readonly DatabaseRestoreResult $restoreResult,
        private readonly ?Throwable $preflightFailure = null,
        private readonly ?Throwable $restoreFailure = null,
    ) {
    }

    public function preflight(string $backupBasename): DatabaseRestorePreflightResult
    {
        $this->preflightCalls[] = $backupBasename;
        if ($this->preflightFailure !== null) {
            throw $this->preflightFailure;
        }

        return $this->preflightResult;
    }

    public function execute(string $backupBasename): DatabaseRestoreResult
    {
        $this->executeCalls[] = $backupBasename;
        if ($this->restoreFailure !== null) {
            throw $this->restoreFailure;
        }

        return $this->restoreResult;
    }
}
