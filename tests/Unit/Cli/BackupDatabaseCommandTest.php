<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use App\Cli\BackupDatabaseCommand;
use App\Services\DatabaseBackupResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupDatabaseCommandTest extends TestCase
{
    private const ROOT = 'C:\\private\\application\\storage\\backups';
    private const PASSWORD = 'fake-database-password';
    private const STDERR = 'raw native stderr with internal details';

    public function testHelpIsSafeAndDoesNotConstructTheBackupService(): void
    {
        $called = false;
        $command = new BackupDatabaseCommand(static function () use (&$called): DatabaseBackupResult {
            $called = true;
            throw new RuntimeException('must not run');
        });

        $result = $command->execute(['--help']);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertFalse($called);
        self::assertStringContainsString('logical SQL backup', $result['stdout']);
        self::assertStringContainsString('private storage/backups', $result['stdout']);
        self::assertStringContainsString('matching versioned manifest containing SHA-256', $result['stdout']);
        self::assertMatchesRegularExpression('/applied\s+migration identity metadata/', $result['stdout']);
        self::assertStringContainsString('must remain private', $result['stdout']);
        self::assertStringContainsString('Scheduling, retention automation, encryption, and offsite/cloud upload are not', $result['stdout']);
        self::assertStringContainsString('separate guarded restore command', $result['stdout']);
    }

    public function testUnknownAndExcessArgumentsReturnUsageFailure(): void
    {
        $command = new BackupDatabaseCommand(static fn (): never => throw new RuntimeException('must not run'));

        foreach ([['--unknown'], ['--help', '--unknown']] as $arguments) {
            $result = $command->execute($arguments);
            self::assertSame(2, $result['exit']);
            self::assertSame('', $result['stdout']);
            self::assertSame("Invalid backup command usage. Use --help for supported usage.\n", $result['stderr']);
        }
    }

    public function testSuccessfulFakeBackupPrintsOnlyBoundedResultMetadata(): void
    {
        $resultObject = new DatabaseBackupResult(
            'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.sql',
            'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.manifest.json',
            12345,
            str_repeat('a', 64),
            '2026-08-01T14:30:00Z',
            'mysql',
            'mysqldump 8.0.44',
        );
        $command = new BackupDatabaseCommand(static fn (): DatabaseBackupResult => $resultObject);

        $result = $command->execute([]);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        foreach ([
            $resultObject->sqlBackupFile,
            $resultObject->manifestFile,
            'Bytes: 12345',
            'SHA-256: ' . str_repeat('a', 64),
            'Created UTC: 2026-08-01T14:30:00Z',
            'Native tool: mysql mysqldump 8.0.44',
        ] as $metadata) {
            self::assertStringContainsString($metadata, $result['stdout']);
        }
        self::assertStringNotContainsString(self::ROOT, $result['stdout']);
        self::assertStringNotContainsString(self::PASSWORD, $result['stdout']);
        self::assertStringNotContainsString('database-name', $result['stdout']);
        self::assertStringNotContainsString('fake-db-host', $result['stdout']);
    }

    public function testExpectedOperationalFailureIsSanitized(): void
    {
        $command = new BackupDatabaseCommand(static function (): never {
            throw new RuntimeException(
                'Database backup could not be created.',
                previous: new RuntimeException(self::PASSWORD . ' ' . self::STDERR . ' ' . self::ROOT),
            );
        });

        $result = $command->execute([]);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame("Database backup could not be created.\n", $result['stderr']);
        self::assertStringNotContainsString(self::PASSWORD, $result['stderr']);
        self::assertStringNotContainsString(self::STDERR, $result['stderr']);
        self::assertStringNotContainsString(self::ROOT, $result['stderr']);
        self::assertStringNotContainsString('Stack trace', $result['stderr']);
    }
}
