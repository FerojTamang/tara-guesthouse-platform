<?php

declare(strict_types=1);

namespace App\Cli;

use App\Services\DatabaseBackupResult;
use Closure;
use Throwable;

/** Bounded CLI boundary for creating one private logical database backup. */
final class BackupDatabaseCommand
{
    private const HELP = <<<'TEXT'
Usage: php bin/backup-database.php [--help]

Creates one logical SQL backup in the private storage/backups directory and
produces a matching versioned manifest containing SHA-256, byte-size, and applied
migration identity metadata. The directory and generated files must remain private
and writable by the CLI account. The command requires mariadb-dump or mysqldump.
Database credentials are carried in a temporary private option file, not command-
line arguments.

Scheduling, retention automation, encryption, and offsite/cloud upload are not
implemented. Restore uses the separate guarded restore command. cPanel and
production verification remain pending.
TEXT;

    /** @var Closure(): DatabaseBackupResult */
    private readonly Closure $backup;

    /** @param callable(): DatabaseBackupResult $backup */
    public function __construct(callable $backup)
    {
        $this->backup = Closure::fromCallable($backup);
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

        if ($arguments !== []) {
            return [
                'exit' => 2,
                'stdout' => '',
                'stderr' => "Invalid backup command usage. Use --help for supported usage.\n",
            ];
        }

        try {
            $result = ($this->backup)();
            if (!$result instanceof DatabaseBackupResult) {
                throw new \RuntimeException();
            }

            $nativeTool = $result->toolFamily;
            if ($result->toolVersion !== null) {
                $nativeTool .= ' ' . $result->toolVersion;
            }

            return [
                'exit' => 0,
                'stdout' => sprintf(
                    "SQL backup: %s\nManifest: %s\nBytes: %d\nSHA-256: %s\nCreated UTC: %s\nNative tool: %s\n",
                    $result->sqlBackupFile,
                    $result->manifestFile,
                    $result->bytes,
                    $result->sha256,
                    $result->createdAtUtc,
                    $nativeTool,
                ),
                'stderr' => '',
            ];
        } catch (Throwable $exception) {
            $message = in_array($exception->getMessage(), [
                'Database backup client is unavailable.',
                'Database backup could not be created.',
                'Database backup output is invalid.',
                'Database backup could not be finalized.',
            ], true)
                ? $exception->getMessage()
                : 'Database backup failed.';

            return ['exit' => 1, 'stdout' => '', 'stderr' => $message . "\n"];
        }
    }
}
