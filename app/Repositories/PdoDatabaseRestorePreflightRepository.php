<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use PDO;
use Throwable;

/** Performs read-only metadata inspection for the configured restore target. */
final readonly class PdoDatabaseRestorePreflightRepository implements DatabaseRestorePreflightRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function inspectTarget(string $databaseName): array
    {
        try {
            $existence = $this->connection->prepare(<<<'SQL'
                SELECT COUNT(*) AS database_count
                FROM information_schema.SCHEMATA
                WHERE SCHEMA_NAME = :database_name
                SQL);
            $existence->execute(['database_name' => $databaseName]);
            $databaseCount = self::integer($existence->fetchColumn());

            if ($databaseCount === 0) {
                return ['exists' => false, 'table_count' => 0, 'view_count' => 0];
            }
            if ($databaseCount !== 1) {
                throw new \RuntimeException();
            }

            $objects = $this->objectCounts($databaseName);

            return [
                'exists' => true,
                'table_count' => $objects['table_count'],
                'view_count' => $objects['view_count'],
            ];
        } catch (Throwable) {
            throw new DatabaseException('Database restore preflight failed.');
        }
    }

    public function inspectRestoredTarget(string $databaseName): array
    {
        try {
            $objects = $this->objectCounts($databaseName);
            $migrationTable = $this->connection->prepare(<<<'SQL'
                SELECT COUNT(*) AS migration_table_count
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :database_name
                  AND TABLE_NAME = 'schema_migrations'
                  AND TABLE_TYPE = 'BASE TABLE'
                SQL);
            $migrationTable->execute(['database_name' => $databaseName]);
            if (self::integer($migrationTable->fetchColumn()) !== 1) {
                throw new \RuntimeException();
            }

            $migrationState = $this->connection->query(
                'SELECT COUNT(*) AS migration_count, MAX(migration) AS latest_migration FROM schema_migrations',
            )?->fetch();
            if (!is_array($migrationState)) {
                throw new \RuntimeException();
            }

            $migrationCount = self::integer($migrationState['migration_count'] ?? null);
            $latestMigration = $migrationState['latest_migration'] ?? null;
            if (
                $migrationCount <= 0
                || !is_string($latestMigration)
                || preg_match('/^[0-9]{14}_[a-z0-9_]+$/D', $latestMigration) !== 1
            ) {
                throw new \RuntimeException();
            }

            return [
                'table_count' => $objects['table_count'],
                'view_count' => $objects['view_count'],
                'migration_count' => $migrationCount,
                'latest_migration' => $latestMigration,
            ];
        } catch (Throwable) {
            throw new DatabaseException('Database restore verification failed.');
        }
    }

    /** @return array{table_count: int, view_count: int} */
    private function objectCounts(string $databaseName): array
    {
        $objects = $this->connection->prepare(<<<'SQL'
            SELECT
                COALESCE(SUM(CASE WHEN TABLE_TYPE = 'BASE TABLE' THEN 1 ELSE 0 END), 0) AS table_count,
                COALESCE(SUM(CASE WHEN TABLE_TYPE = 'VIEW' THEN 1 ELSE 0 END), 0) AS view_count
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = :database_name
              AND TABLE_TYPE IN ('BASE TABLE', 'VIEW')
            SQL);
        $objects->execute(['database_name' => $databaseName]);
        $row = $objects->fetch();
        if (!is_array($row)) {
            throw new \RuntimeException();
        }

        return [
            'table_count' => self::integer($row['table_count'] ?? null),
            'view_count' => self::integer($row['view_count'] ?? null),
        ];
    }

    private static function integer(mixed $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 0) {
            throw new \RuntimeException();
        }

        return $integer;
    }
}
