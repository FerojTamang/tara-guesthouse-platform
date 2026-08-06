<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Immutable, non-secret outcome of a completed and verified logical database restore. */
final readonly class DatabaseRestoreResult
{
    public function __construct(
        public string $sqlBackupFile,
        public string $manifestFile,
        public int $bytes,
        public string $sha256,
        public string $restoredAtUtc,
        public string $toolFamily,
        public ?string $toolVersion,
        public int $targetTableCount,
        public int $targetViewCount,
        public int $migrationCount,
        public string $latestMigration,
        public bool $restoreExecuted = true,
        public bool $restoreVerified = true,
    ) {
        $restoredAt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $restoredAtUtc,
            new DateTimeZone('UTC'),
        );

        if (
            preg_match('/^tara-guest-house-\d{8}T\d{6}Z-[a-f0-9]{12}\.sql$/D', $sqlBackupFile) !== 1
            || $manifestFile !== substr($sqlBackupFile, 0, -4) . '.manifest.json'
            || $bytes <= 0
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
            || $restoredAt === false
            || $restoredAt->format('Y-m-d\TH:i:s\Z') !== $restoredAtUtc
            || !in_array($toolFamily, ['mariadb', 'mysql'], true)
            || ($toolVersion !== null && (strlen($toolVersion) > 80 || preg_match('/[\x00\r\n]/', $toolVersion) === 1))
            || $targetTableCount <= 0
            || $targetViewCount < 0
            || $migrationCount <= 0
            || preg_match('/^[0-9]{14}_[a-z0-9_]+$/D', $latestMigration) !== 1
            || !$restoreExecuted
            || !$restoreVerified
        ) {
            throw new InvalidArgumentException('Database restore result is invalid.');
        }
    }
}
