<?php

declare(strict_types=1);

namespace App\Cli;

use App\Services\DatabaseRestoreOperations;
use App\Services\DatabaseRestorePreflightResult;
use App\Services\DatabaseRestoreResult;
use Closure;
use Throwable;

/** Strict CLI boundary for restore preflight and explicitly confirmed execution. */
final class RestoreDatabaseCommand
{
    private const HELP = <<<'TEXT'
Usage:
  php bin/restore-database.php <backup-basename.sql>
  php bin/restore-database.php <backup-basename.sql> --execute --confirm-database=<exact-name>
  php bin/restore-database.php <backup-basename.sql> --execute --confirm-database=<exact-name> --confirm-environment=<exact-environment>
  php bin/restore-database.php --help

Restore defaults to preflight. Preflight verifies the manifest, byte count,
SHA-256, schema-engine compatibility, native client, and that the configured
application database already exists with zero tables and zero views. It does not
import SQL.

Execution requires --execute and exact database confirmation. Staging and
production also require exact environment confirmation. SQL is supplied to the
native client through stdin and credentials use a temporary private option file.
A zero native exit code is not enough: the restored schema_migrations state and
object counts are read back and must match manifest metadata when recorded. The
backup and manifest are retained.

There is no force mode or automatic database replacement. The command never uses
DROP DATABASE, CREATE DATABASE, or TRUNCATE. A failed or unverified import may
leave a partial target; discard that disposable database rather than retrying in
place. Production/cPanel restore validation remains pending.
TEXT;

    /** @var Closure(): array{database: string, environment: string} */
    private readonly Closure $configuration;

    /** @var Closure(): DatabaseRestoreOperations */
    private readonly Closure $restore;

    /**
     * @param callable(): array{database: string, environment: string} $configuration
     * @param callable(): DatabaseRestoreOperations $restore
     */
    public function __construct(callable $configuration, callable $restore)
    {
        $this->configuration = Closure::fromCallable($configuration);
        $this->restore = Closure::fromCallable($restore);
    }

    /**
     * @param list<string> $arguments
     * @return array{exit: int, stdout: string, stderr: string}
     */
    public function execute(array $arguments): array
    {
        if ($arguments === ['--help']) {
            return ['exit' => 0, 'stdout' => self::HELP . "\n", 'stderr' => ''];
        }

        $backupBasename = $arguments[0] ?? null;
        if (
            !is_string($backupBasename)
            || preg_match('/^tara-guest-house-\d{8}T\d{6}Z-[a-f0-9]{12}\.sql$/D', $backupBasename) !== 1
        ) {
            return self::usageFailure();
        }

        $options = array_slice($arguments, 1);
        if ($options === []) {
            try {
                $service = ($this->restore)();
                if (!$service instanceof DatabaseRestoreOperations) {
                    throw new \RuntimeException();
                }

                return [
                    'exit' => 0,
                    'stdout' => self::preflightOutput($service->preflight($backupBasename)),
                    'stderr' => '',
                ];
            } catch (Throwable $exception) {
                return self::operationalFailure($exception);
            }
        }

        if ($options[0] !== '--execute' || count($options) > 3) {
            return self::usageFailure();
        }
        if (count($options) < 2) {
            return self::confirmationFailure();
        }

        if (!str_starts_with($options[1], '--confirm-database=')) {
            return self::confirmationFailure();
        }
        $databaseConfirmation = substr($options[1], strlen('--confirm-database='));
        if ($databaseConfirmation === '') {
            return self::confirmationFailure();
        }

        $environmentConfirmation = null;
        if (isset($options[2])) {
            if (!str_starts_with($options[2], '--confirm-environment=')) {
                return self::usageFailure();
            }
            $environmentConfirmation = substr($options[2], strlen('--confirm-environment='));
            if ($environmentConfirmation === '') {
                return self::confirmationFailure();
            }
        }

        try {
            $configuration = ($this->configuration)();
            if (
                !isset($configuration['database'], $configuration['environment'])
                || !is_string($configuration['database'])
                || $configuration['database'] === ''
                || !is_string($configuration['environment'])
                || !in_array($configuration['environment'], ['local', 'testing', 'staging', 'production'], true)
            ) {
                throw new \RuntimeException();
            }
        } catch (Throwable) {
            return ['exit' => 1, 'stdout' => '', 'stderr' => "Database restore command failed.\n"];
        }

        $protected = in_array($configuration['environment'], ['staging', 'production'], true);
        if (
            !hash_equals($configuration['database'], $databaseConfirmation)
            || ($protected && $environmentConfirmation !== $configuration['environment'])
            || (!$protected && $environmentConfirmation !== null)
        ) {
            return self::confirmationFailure();
        }

        try {
            $service = ($this->restore)();
            if (!$service instanceof DatabaseRestoreOperations) {
                throw new \RuntimeException();
            }

            return [
                'exit' => 0,
                'stdout' => self::restoreOutput($service->execute($backupBasename)),
                'stderr' => '',
            ];
        } catch (Throwable $exception) {
            return self::operationalFailure($exception);
        }
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private static function usageFailure(): array
    {
        return [
            'exit' => 2,
            'stdout' => '',
            'stderr' => "Invalid restore command usage. Use --help for supported usage.\n",
        ];
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private static function confirmationFailure(): array
    {
        return ['exit' => 2, 'stdout' => '', 'stderr' => "Restore execution confirmation is invalid.\n"];
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private static function operationalFailure(Throwable $exception): array
    {
        $message = in_array($exception->getMessage(), [
            'Database restore client is unavailable.',
            'Database restore preflight failed.',
            'The configured restore database is not empty.',
            'Database restore could not be completed.',
        ], true)
            ? $exception->getMessage()
            : 'Database restore command failed.';

        return ['exit' => 1, 'stdout' => '', 'stderr' => $message . "\n"];
    }

    private static function preflightOutput(DatabaseRestorePreflightResult $result): string
    {
        return sprintf(
            "SQL backup: %s\nManifest: %s\nBytes: %d\nSHA-256: %s\nBackup created UTC: %s\nBackup tool: %s\nRestore tool: %s\nTarget tables: %d\nTarget views: %d\nExpected migrations: %s\nExpected latest migration: %s\nRestore not executed.\n",
            $result->sqlBackupFile,
            $result->manifestFile,
            $result->bytes,
            $result->sha256,
            $result->backupCreatedAtUtc,
            self::tool($result->backupToolFamily, $result->backupToolVersion),
            self::tool($result->restoreToolFamily, $result->restoreToolVersion),
            $result->targetTableCount,
            $result->targetViewCount,
            $result->expectedMigrationCount === null ? 'not recorded' : (string) $result->expectedMigrationCount,
            $result->expectedLatestMigration ?? 'not recorded',
        );
    }

    private static function restoreOutput(DatabaseRestoreResult $result): string
    {
        return sprintf(
            "SQL backup: %s\nManifest: %s\nBytes: %d\nSHA-256: %s\nRestored UTC: %s\nRestore tool: %s\nVerified tables: %d\nVerified views: %d\nVerified migrations: %d\nLatest migration: %s\nRestore executed and verified.\n",
            $result->sqlBackupFile,
            $result->manifestFile,
            $result->bytes,
            $result->sha256,
            $result->restoredAtUtc,
            self::tool($result->toolFamily, $result->toolVersion),
            $result->targetTableCount,
            $result->targetViewCount,
            $result->migrationCount,
            $result->latestMigration,
        );
    }

    private static function tool(string $family, ?string $version): string
    {
        return $version === null ? $family : $family . ' ' . $version;
    }
}
