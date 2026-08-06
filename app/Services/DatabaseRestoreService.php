<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DatabaseRestorePreflightRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/** Validates restore prerequisites and coordinates an explicitly confirmed logical import. */
final class DatabaseRestoreService implements DatabaseRestoreOperations
{
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /** @var (Closure(int): string)|null */
    private readonly ?Closure $optionFileRandom;

    /**
     * @param array<string, mixed> $database Validated database configuration.
     * @param (callable(): DateTimeImmutable)|null $clock
     * @param (callable(int): string)|null $optionFileRandom
     */
    public function __construct(
        private readonly DatabaseBackupRoot $root,
        private readonly array $database,
        private readonly DatabaseRestorePreflightRepository $repository,
        private readonly DatabaseNativeClientResolver $clientResolver,
        private readonly DatabaseNativeClientCommandBuilder $commandBuilder,
        private readonly NativeProcessRunner $processRunner,
        ?callable $clock = null,
        ?callable $optionFileRandom = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        $this->optionFileRandom = $optionFileRandom === null ? null : Closure::fromCallable($optionFileRandom);
    }

    public function preflight(string $backupBasename): DatabaseRestorePreflightResult
    {
        return $this->validate($backupBasename)['result'];
    }

    public function execute(string $backupBasename): DatabaseRestoreResult
    {
        $validated = $this->validate($backupBasename);
        $preflight = $validated['result'];
        $client = $validated['client'];
        $sqlPath = $validated['sql_path'];
        $expectedMigrationCount = $validated['expected_migration_count'];
        $expectedLatestMigration = $validated['expected_latest_migration'];
        $optionFile = null;

        try {
            $optionFile = new DatabaseClientOptionFile(
                $this->root,
                $this->database,
                $this->optionFileRandom,
            );
            $command = $this->commandBuilder->build(
                'restore',
                $client['family'],
                $client['executable'],
                $optionFile->path(),
                $this->databaseName(),
                $this->databaseCharset(),
            );
            $expectedArguments = [
                '--defaults-extra-file=' . $optionFile->path(),
                '--default-character-set=' . $this->databaseCharset(),
                $this->databaseName(),
            ];

            if (
                $command['executable'] !== $client['executable']
                || $command['family'] !== $client['family']
                || $command['operation'] !== 'restore'
                || $command['arguments'] !== $expectedArguments
            ) {
                throw new RuntimeException();
            }

            $request = new NativeProcessRequest(
                $command['executable'],
                $command['arguments'],
                $sqlPath,
                null,
            );
            $processResult = $this->processRunner->run($request);
            if ($processResult->exitCode !== 0) {
                throw new RuntimeException();
            }

            $verification = $this->repository->inspectRestoredTarget($this->databaseName());
            if (
                !is_int($verification['table_count'] ?? null)
                || !is_int($verification['view_count'] ?? null)
                || !is_int($verification['migration_count'] ?? null)
                || !is_string($verification['latest_migration'] ?? null)
                || $verification['table_count'] <= 0
                || $verification['view_count'] < 0
                || $verification['migration_count'] <= 0
                || preg_match('/^[0-9]{14}_[a-z0-9_]+$/D', $verification['latest_migration']) !== 1
                || ($expectedMigrationCount !== null && $verification['migration_count'] !== $expectedMigrationCount)
                || ($expectedLatestMigration !== null && !hash_equals(
                    $expectedLatestMigration,
                    $verification['latest_migration'],
                ))
            ) {
                throw new RuntimeException();
            }

            $restoredAt = ($this->clock)();
            if (!$restoredAt instanceof DateTimeImmutable) {
                throw new RuntimeException();
            }

            return new DatabaseRestoreResult(
                $preflight->sqlBackupFile,
                $preflight->manifestFile,
                $preflight->bytes,
                $preflight->sha256,
                $restoredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                $preflight->restoreToolFamily,
                $preflight->restoreToolVersion,
                $verification['table_count'],
                $verification['view_count'],
                $verification['migration_count'],
                $verification['latest_migration'],
                true,
                true,
            );
        } catch (Throwable) {
            throw new RuntimeException('Database restore could not be completed.');
        } finally {
            if ($optionFile instanceof DatabaseClientOptionFile) {
                $optionFile->cleanup();
            }
        }
    }

    /**
     * @return array{
     *   result: DatabaseRestorePreflightResult,
     *   client: array{executable: string, family: string, version: string|null},
     *   sql_path: string,
     *   expected_migration_count: int|null,
     *   expected_latest_migration: string|null
     * }
     */
    private function validate(string $backupBasename): array
    {
        try {
            if (preg_match('/^tara-guest-house-\d{8}T\d{6}Z-[a-f0-9]{12}\.sql$/D', $backupBasename) !== 1) {
                throw new RuntimeException();
            }

            $sqlPath = $this->root->resolve($backupBasename);
            $manifestBasename = substr($backupBasename, 0, -4) . '.manifest.json';
            $manifestPath = $this->root->resolve($manifestBasename);

            if (!self::regularFile($sqlPath) || !self::validSqlPrefix($sqlPath)) {
                throw new RuntimeException();
            }

            $manifest = DatabaseBackupManifest::fromJson(self::readManifest($manifestPath));
            if ($manifest->backupFile !== $backupBasename || $manifest->manifestFile() !== $manifestBasename) {
                throw new RuntimeException();
            }

            $bytes = filesize($sqlPath);
            $sha256 = hash_file('sha256', $sqlPath);
            if (
                !is_int($bytes)
                || $bytes <= 0
                || !is_string($sha256)
                || $bytes !== $manifest->bytes
                || !hash_equals($manifest->sha256, $sha256)
            ) {
                throw new RuntimeException();
            }

            $databaseName = $this->databaseName();
            $databaseEngine = $this->databaseEngine();
            if ($manifest->databaseEngine !== $databaseEngine) {
                throw new RuntimeException();
            }
        } catch (Throwable) {
            throw new RuntimeException('Database restore preflight failed.');
        }

        try {
            $target = $this->repository->inspectTarget($databaseName);
            if (
                !is_bool($target['exists'] ?? null)
                || !is_int($target['table_count'] ?? null)
                || !is_int($target['view_count'] ?? null)
                || $target['table_count'] < 0
                || $target['view_count'] < 0
            ) {
                throw new RuntimeException();
            }
        } catch (Throwable) {
            throw new RuntimeException('Database restore preflight failed.');
        }

        if (!$target['exists']) {
            throw new RuntimeException('Database restore preflight failed.');
        }
        if ($target['table_count'] !== 0 || $target['view_count'] !== 0) {
            throw new RuntimeException('The configured restore database is not empty.');
        }

        try {
            $client = $this->clientResolver->resolve('restore');
            if (
                !isset($client['executable'], $client['family'])
                || !is_string($client['executable'])
                || !is_string($client['family'])
                || !in_array($client['family'], ['mariadb', 'mysql'], true)
                || !array_key_exists('version', $client)
                || ($client['version'] !== null && !is_string($client['version']))
            ) {
                throw new RuntimeException();
            }
        } catch (Throwable) {
            throw new RuntimeException('Database restore client is unavailable.');
        }

        return [
            'result' => new DatabaseRestorePreflightResult(
                $backupBasename,
                $manifestBasename,
                $bytes,
                $sha256,
                $manifest->createdAtUtc,
                $manifest->toolFamily,
                $manifest->toolVersion,
                $client['family'],
                self::boundedVersion($client['version']),
                $databaseEngine,
                $target['table_count'],
                $target['view_count'],
                $manifest->migrationCount,
                $manifest->latestMigration,
                false,
            ),
            'client' => $client,
            'sql_path' => $sqlPath,
            'expected_migration_count' => $manifest->migrationCount,
            'expected_latest_migration' => $manifest->latestMigration,
        ];
    }

    private function databaseName(): string
    {
        if (!isset($this->database['database']) || !is_string($this->database['database'])) {
            throw new RuntimeException();
        }

        return $this->database['database'];
    }

    private function databaseCharset(): string
    {
        if (!isset($this->database['charset']) || !is_string($this->database['charset'])) {
            throw new RuntimeException();
        }

        return $this->database['charset'];
    }

    private function databaseEngine(): string
    {
        if (!isset($this->database['driver']) || $this->database['driver'] !== 'mysql') {
            throw new RuntimeException();
        }

        return 'mysql';
    }

    private static function regularFile(string $path): bool
    {
        return is_file($path) && !is_link($path);
    }

    private static function validSqlPrefix(string $path): bool
    {
        $bytes = filesize($path);
        if (!is_int($bytes) || $bytes <= 0) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $prefix = fread($handle, 65_536);
        } finally {
            fclose($handle);
        }

        return is_string($prefix)
            && trim($prefix) !== ''
            && preg_match(
                '/(?:CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE|(?:^|[;\r\n])\s*SET\s+|MySQL dump|MariaDB dump)/i',
                $prefix,
            ) === 1;
    }

    private static function readManifest(string $path): string
    {
        if (!self::regularFile($path)) {
            throw new RuntimeException();
        }

        $bytes = filesize($path);
        if (!is_int($bytes) || $bytes <= 0 || $bytes > 16_384) {
            throw new RuntimeException();
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            throw new RuntimeException();
        }

        return $json;
    }

    private static function boundedVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = trim(str_replace(["\0", "\r", "\n"], ' ', $version));

        return $version === '' ? null : substr($version, 0, 80);
    }
}
