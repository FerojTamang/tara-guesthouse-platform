<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\DatabaseException;
use DirectoryIterator;
use PDO;
use Throwable;

/**
 * Discovers, validates, applies, and rolls back ordered database migrations.
 */
final class MigrationRunner
{
    private const LOCK_TIMEOUT_SECONDS = 5;

    private readonly PDO $connection;

    private readonly string $migrationPath;

    public function __construct(
        PDO $connection,
        string $migrationPath,
    ) {
        $resolvedPath = realpath($migrationPath);

        if (
            $resolvedPath === false
            || !is_dir($resolvedPath)
            || !is_readable($resolvedPath)
            || is_link($migrationPath)
        ) {
            throw new DatabaseException('The migration directory is unavailable.');
        }

        $this->connection = $connection;
        $this->migrationPath = $resolvedPath;
    }

    /**
     * @return array<string, array{migration: Migration, checksum: string, path: string}>
     */
    public function discover(): array
    {
        $files = [];

        foreach (new DirectoryIterator($this->migrationPath) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->isLink()) {
                throw new DatabaseException('Migration symlinks are not allowed.');
            }

            $filename = $file->getFilename();

            if (preg_match('/^(\d{14})_([a-z][a-z0-9]*(?:_[a-z0-9]+)*)\.php$/D', $filename, $matches) !== 1) {
                throw new DatabaseException('A migration filename is invalid.');
            }

            $identifier = substr($filename, 0, -4);
            $timestamp = $matches[1];

            if (isset($files[$identifier])) {
                throw new DatabaseException('A duplicate migration identifier was found.');
            }

            foreach ($files as $existingIdentifier => $existing) {
                if (str_starts_with($existingIdentifier, $timestamp . '_')) {
                    throw new DatabaseException('A duplicate migration timestamp was found.');
                }
            }

            $path = realpath($file->getPathname());

            if ($path === false || !$this->isDirectFile($path)) {
                throw new DatabaseException('A migration path escapes the migration directory.');
            }

            $checksum = hash_file('sha256', $path);

            if ($checksum === false) {
                throw new DatabaseException('A migration checksum could not be calculated.');
            }

            try {
                $migration = $this->loadMigration($path);
            } catch (Throwable) {
                throw new DatabaseException('A migration file could not be loaded.');
            }

            if (!$migration instanceof Migration) {
                throw new DatabaseException('Each migration file must return one Migration instance.');
            }

            $files[$identifier] = [
                'migration' => $migration,
                'checksum' => $checksum,
                'path' => $path,
            ];
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return list<array{migration: string, status: string, batch: int|null, executed_at: string|null}>
     */
    public function status(): array
    {
        $this->ensureMigrationTable();
        $discovered = $this->discover();
        $applied = $this->applied();
        $this->validateAppliedMigrations($discovered, $applied);
        $status = [];

        foreach ($discovered as $identifier => $details) {
            $record = $applied[$identifier] ?? null;
            $status[] = [
                'migration' => $identifier,
                'status' => $record === null ? 'pending' : 'applied',
                'batch' => $record['batch'] ?? null,
                'executed_at' => $record['executed_at'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * @return array<string, array{migration: Migration, checksum: string, path: string}>
     */
    public function pending(): array
    {
        $this->ensureMigrationTable();
        $discovered = $this->discover();
        $applied = $this->applied();
        $this->validateAppliedMigrations($discovered, $applied);

        return array_diff_key($discovered, $applied);
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        if ($this->connection->inTransaction()) {
            throw new DatabaseException('Migrations cannot run inside an existing transaction.');
        }

        return $this->withLock(function (): array {
            $this->ensureMigrationTable();
            $pending = $this->pending();

            if ($pending === []) {
                return [];
            }

            $batch = $this->nextBatch();
            $applied = [];

            foreach ($pending as $identifier => $details) {
                $this->applyMigration($identifier, $details, $batch);
                $applied[] = $identifier;
            }

            return $applied;
        });
    }

    /**
     * @return list<string>
     */
    public function rollbackLastBatch(): array
    {
        if ($this->connection->inTransaction()) {
            throw new DatabaseException('Migration rollback cannot run inside an existing transaction.');
        }

        return $this->withLock(function (): array {
            $this->ensureMigrationTable();
            $discovered = $this->discover();
            $applied = $this->applied();
            $this->validateAppliedMigrations($discovered, $applied);

            if ($applied === []) {
                return [];
            }

            $latestBatch = max(array_column($applied, 'batch'));
            $identifiers = array_keys(array_filter(
                $applied,
                static fn (array $record): bool => $record['batch'] === $latestBatch,
            ));
            rsort($identifiers, SORT_STRING);
            $rolledBack = [];

            foreach ($identifiers as $identifier) {
                $this->rollbackMigration($identifier, $discovered[$identifier]['migration']);
                $rolledBack[] = $identifier;
            }

            return $rolledBack;
        });
    }

    private function ensureMigrationTable(): void
    {
        try {
            $this->connection->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations ('
                . 'migration VARCHAR(191) NOT NULL PRIMARY KEY,'
                . 'checksum CHAR(64) NOT NULL,'
                . 'batch INT UNSIGNED NOT NULL,'
                . 'executed_at DATETIME NOT NULL'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            );
        } catch (Throwable) {
            throw new DatabaseException('The migration history table could not be prepared.');
        }
    }

    /**
     * @return array<string, array{checksum: string, batch: int, executed_at: string}>
     */
    private function applied(): array
    {
        try {
            $statement = $this->connection->query(
                'SELECT migration, checksum, batch, executed_at FROM schema_migrations ORDER BY migration ASC',
            );

            if ($statement === false) {
                throw new DatabaseException('Migration history could not be read.');
            }

            $records = [];

            foreach ($statement->fetchAll() as $row) {
                if (
                    !is_array($row)
                    || !is_string($row['migration'] ?? null)
                    || !is_string($row['checksum'] ?? null)
                    || !is_numeric($row['batch'] ?? null)
                    || !is_string($row['executed_at'] ?? null)
                ) {
                    throw new DatabaseException('Migration history contains an invalid record.');
                }

                $records[$row['migration']] = [
                    'checksum' => $row['checksum'],
                    'batch' => (int) $row['batch'],
                    'executed_at' => $row['executed_at'],
                ];
            }

            return $records;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('Migration history could not be read.');
        }
    }

    /**
     * @param array<string, array{migration: Migration, checksum: string, path: string}> $discovered
     * @param array<string, array{checksum: string, batch: int, executed_at: string}> $applied
     */
    private function validateAppliedMigrations(array $discovered, array $applied): void
    {
        foreach ($applied as $identifier => $record) {
            if (!isset($discovered[$identifier])) {
                throw new DatabaseException('An applied migration file is missing.');
            }

            if (!hash_equals($record['checksum'], $discovered[$identifier]['checksum'])) {
                throw new DatabaseException('An applied migration checksum does not match its file.');
            }
        }
    }

    private function nextBatch(): int
    {
        try {
            $value = $this->connection->query('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations')?->fetchColumn();

            if (!is_numeric($value)) {
                throw new DatabaseException('The next migration batch could not be determined.');
            }

            return (int) $value + 1;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The next migration batch could not be determined.');
        }
    }

    /**
     * @param array{migration: Migration, checksum: string, path: string} $details
     */
    private function applyMigration(string $identifier, array $details, int $batch): void
    {
        $transactional = $details['migration']->transactional();
        $startedTransaction = false;

        try {
            if ($transactional) {
                $this->connection->beginTransaction();
                $startedTransaction = true;
            }

            $details['migration']->up($this->connection);
            $statement = $this->connection->prepare(
                'INSERT INTO schema_migrations (migration, checksum, batch, executed_at) '
                . 'VALUES (:migration, :checksum, :batch, UTC_TIMESTAMP())',
            );
            $statement->execute([
                'migration' => $identifier,
                'checksum' => $details['checksum'],
                'batch' => $batch,
            ]);

            if ($startedTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new DatabaseException('A migration could not be applied.');
        }
    }

    private function rollbackMigration(string $identifier, Migration $migration): void
    {
        $transactional = $migration->transactional();
        $startedTransaction = false;

        try {
            if ($transactional) {
                $this->connection->beginTransaction();
                $startedTransaction = true;
            }

            $migration->down($this->connection);
            $statement = $this->connection->prepare('DELETE FROM schema_migrations WHERE migration = :migration');
            $statement->execute(['migration' => $identifier]);

            if ($startedTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new DatabaseException('A migration could not be rolled back.');
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function withLock(callable $operation): mixed
    {
        $lockName = $this->lockName();
        $acquired = false;

        try {
            $statement = $this->connection->prepare('SELECT GET_LOCK(:lock_name, :timeout)');
            $statement->bindValue('lock_name', $lockName);
            $statement->bindValue('timeout', self::LOCK_TIMEOUT_SECONDS, PDO::PARAM_INT);
            $statement->execute();
            $acquired = (int) $statement->fetchColumn() === 1;

            if (!$acquired) {
                throw new DatabaseException('The migration lock could not be acquired.');
            }

            return $operation();
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The migration lock operation failed.');
        } finally {
            if ($acquired) {
                try {
                    $statement = $this->connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
                    $statement->execute(['lock_name' => $lockName]);
                } catch (Throwable) {
                    // The command is already failing safely or has completed; never expose connection details.
                }
            }
        }
    }

    private function lockName(): string
    {
        try {
            $database = $this->connection->query('SELECT DATABASE()')?->fetchColumn();

            if (!is_string($database) || $database === '') {
                throw new DatabaseException('The active database could not be identified.');
            }

            return 'tgh_migrations_' . substr(hash('sha256', $database), 0, 40);
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The active database could not be identified.');
        }
    }

    private function isDirectFile(string $path): bool
    {
        $expectedDirectory = $this->migrationPath;
        $actualDirectory = dirname($path);

        if (DIRECTORY_SEPARATOR === '\\') {
            return strtolower($actualDirectory) === strtolower($expectedDirectory);
        }

        return $actualDirectory === $expectedDirectory;
    }

    private function loadMigration(string $path): mixed
    {
        $bufferLevel = ob_get_level();
        ob_start(static fn (string $output): string => '');

        try {
            return (static fn (string $migrationFile): mixed => require $migrationFile)($path);
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }
}
