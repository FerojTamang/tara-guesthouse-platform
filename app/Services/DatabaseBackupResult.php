<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Immutable, non-secret outcome of a completed logical database backup. */
final readonly class DatabaseBackupResult
{
    public function __construct(
        public string $sqlBackupFile,
        public string $manifestFile,
        public int $bytes,
        public string $sha256,
        public string $createdAtUtc,
        public string $toolFamily,
        public ?string $toolVersion,
    ) {
        $createdAt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $createdAtUtc,
            new DateTimeZone('UTC'),
        );

        if (
            preg_match('/^tara-guest-house-\d{8}T\d{6}Z-[a-f0-9]{12}\.sql$/D', $sqlBackupFile) !== 1
            || $manifestFile !== substr($sqlBackupFile, 0, -4) . '.manifest.json'
            || $bytes <= 0
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
            || $createdAt === false
            || $createdAt->format('Y-m-d\TH:i:s\Z') !== $createdAtUtc
            || !in_array($toolFamily, ['mariadb', 'mysql'], true)
            || ($toolVersion !== null && (strlen($toolVersion) > 80 || preg_match('/[\x00\r\n]/', $toolVersion) === 1))
        ) {
            throw new InvalidArgumentException('Database backup result is invalid.');
        }
    }
}
