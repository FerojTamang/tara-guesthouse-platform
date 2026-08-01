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
            $databaseCount = $existence->fetchColumn();

            if (filter_var($databaseCount, FILTER_VALIDATE_INT) === false) {
                throw new \RuntimeException();
            }

            if ((int) $databaseCount === 0) {
                return ['exists' => false, 'table_count' => 0, 'view_count' => 0];
            }

            if ((int) $databaseCount !== 1) {
                throw new \RuntimeException();
            }

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

            $tableCount = filter_var($row['table_count'] ?? null, FILTER_VALIDATE_INT);
            $viewCount = filter_var($row['view_count'] ?? null, FILTER_VALIDATE_INT);
            if ($tableCount === false || $viewCount === false || $tableCount < 0 || $viewCount < 0) {
                throw new \RuntimeException();
            }

            return ['exists' => true, 'table_count' => $tableCount, 'view_count' => $viewCount];
        } catch (Throwable) {
            throw new DatabaseException('Database restore preflight failed.');
        }
    }
}
