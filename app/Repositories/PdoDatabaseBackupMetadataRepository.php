<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use PDO;
use Throwable;

/** Reads bounded, non-secret schema identity metadata for backup verification. */
final readonly class PdoDatabaseBackupMetadataRepository implements DatabaseBackupMetadataRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function read(): array
    {
        try {
            $statement = $this->connection->query(
                'SELECT COUNT(*) AS migration_count, MAX(migration) AS latest_migration FROM schema_migrations',
            );
            $row = $statement?->fetch();

            if (!is_array($row)) {
                throw new \RuntimeException();
            }

            $migrationCount = filter_var($row['migration_count'] ?? null, FILTER_VALIDATE_INT);
            $latestMigration = $row['latest_migration'] ?? null;
            if (
                $migrationCount === false
                || $migrationCount <= 0
                || !is_string($latestMigration)
                || preg_match('/^[0-9]{14}_[a-z0-9_]+$/D', $latestMigration) !== 1
            ) {
                throw new \RuntimeException();
            }

            return [
                'migration_count' => $migrationCount,
                'latest_migration' => $latestMigration,
            ];
        } catch (Throwable) {
            throw new DatabaseException('Database backup metadata could not be read.');
        }
    }
}
