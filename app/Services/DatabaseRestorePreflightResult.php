<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Immutable, bounded evidence that restore preflight passed without importing SQL. */
final readonly class DatabaseRestorePreflightResult
{
    public function __construct(
        public string $sqlBackupFile,
        public string $manifestFile,
        public int $bytes,
        public string $sha256,
        public string $backupCreatedAtUtc,
        public string $backupToolFamily,
        public ?string $backupToolVersion,
        public string $restoreToolFamily,
        public ?string $restoreToolVersion,
        public string $databaseEngine,
        public int $targetTableCount,
        public int $targetViewCount,
        public bool $restoreExecuted = false,
    ) {
        $createdAt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $backupCreatedAtUtc,
            new DateTimeZone('UTC'),
        );

        if (
            preg_match('/^tara-guest-house-\d{8}T\d{6}Z-[a-f0-9]{12}\.sql$/D', $sqlBackupFile) !== 1
            || $manifestFile !== substr($sqlBackupFile, 0, -4) . '.manifest.json'
            || $bytes <= 0
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
            || $createdAt === false
            || $createdAt->format('Y-m-d\TH:i:s\Z') !== $backupCreatedAtUtc
            || !in_array($backupToolFamily, ['mariadb', 'mysql'], true)
            || !in_array($restoreToolFamily, ['mariadb', 'mysql'], true)
            || !in_array($databaseEngine, ['mariadb', 'mysql'], true)
            || !self::validVersion($backupToolVersion)
            || !self::validVersion($restoreToolVersion)
            || $targetTableCount !== 0
            || $targetViewCount !== 0
            || $restoreExecuted
        ) {
            throw new InvalidArgumentException('Database restore preflight result is invalid.');
        }
    }

    private static function validVersion(?string $version): bool
    {
        return $version === null
            || (strlen($version) <= 80 && preg_match('/[\x00\r\n]/', $version) !== 1);
    }
}
