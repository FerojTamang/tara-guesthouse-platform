<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** Immutable, non-secret integrity metadata for one logical SQL backup. */
final readonly class DatabaseBackupManifest
{
    public const APPLICATION = 'tara-guest-house';
    public const VERSION = 1;

    public function __construct(
        public string $createdAtUtc,
        public string $backupFile,
        public string $sha256,
        public int $bytes,
        public string $databaseEngine,
        public string $toolFamily,
        public ?string $toolVersion = null,
        public ?string $applicationRevision = null,
        public ?int $migrationCount = null,
        public ?string $latestMigration = null,
    ) {
        if (
            !self::validUtc($createdAtUtc)
            || preg_match('/^tara-guest-house-\d{8}T\d{6}Z-[a-f0-9]{12}\.sql$/D', $backupFile) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
            || $bytes <= 0
            || !in_array($databaseEngine, ['mariadb', 'mysql'], true)
            || !in_array($toolFamily, ['mariadb', 'mysql'], true)
            || ($toolVersion !== null && (strlen($toolVersion) > 80 || preg_match('/[\x00\r\n]/', $toolVersion) === 1))
            || ($applicationRevision !== null && preg_match('/^[a-f0-9]{7,64}$/D', $applicationRevision) !== 1)
            || ($migrationCount !== null && $migrationCount < 0)
            || ($latestMigration !== null && preg_match('/^[0-9]{14}_[a-z0-9_]+$/D', $latestMigration) !== 1)
        ) {
            throw new InvalidArgumentException('Backup manifest is invalid.');
        }
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'format_version' => self::VERSION, 'application' => self::APPLICATION,
            'created_at_utc' => $this->createdAtUtc, 'backup_file' => $this->backupFile,
            'sha256' => $this->sha256, 'bytes' => $this->bytes,
            'database_engine' => $this->databaseEngine, 'backup_format' => 'logical_sql',
            'tool_family' => $this->toolFamily, 'tool_version' => $this->toolVersion,
            'application_revision' => $this->applicationRevision,
            'migration_count' => $this->migrationCount, 'latest_migration' => $this->latestMigration,
        ];
    }

    public function toJson(): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Backup manifest is invalid.', 0, $exception);
        }
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Backup manifest is invalid.', 0, $exception);
        }
        $keys = ['format_version','application','created_at_utc','backup_file','sha256','bytes','database_engine','backup_format','tool_family','tool_version','application_revision','migration_count','latest_migration'];
        if (!is_array($data) || array_keys($data) !== $keys || $data['format_version'] !== 1 || $data['application'] !== self::APPLICATION || $data['backup_format'] !== 'logical_sql' || !is_string($data['created_at_utc']) || !is_string($data['backup_file']) || !is_string($data['sha256']) || !is_int($data['bytes']) || !is_string($data['database_engine']) || !is_string($data['tool_family']) || !is_null($data['tool_version']) && !is_string($data['tool_version']) || !is_null($data['application_revision']) && !is_string($data['application_revision']) || !is_null($data['migration_count']) && !is_int($data['migration_count']) || !is_null($data['latest_migration']) && !is_string($data['latest_migration'])) throw new InvalidArgumentException('Backup manifest is invalid.');
        return new self($data['created_at_utc'],$data['backup_file'],$data['sha256'],$data['bytes'],$data['database_engine'],$data['tool_family'],$data['tool_version'],$data['application_revision'],$data['migration_count'],$data['latest_migration']);
    }

    public function manifestFile(): string
    {
        return substr($this->backupFile, 0, -4) . '.manifest.json';
    }

    private static function validUtc(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) !== 1) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d\\TH:i:s\\Z') === $value;
    }
}
