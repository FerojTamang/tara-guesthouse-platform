<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/** Coordinates one private logical SQL backup with bounded schema metadata capture. */
final class DatabaseBackupService
{
    /** @var array<string, mixed> */
    private readonly array $database;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /** @var (Closure(int): string)|null */
    private readonly ?Closure $optionFileRandom;

    /** @var (Closure(): array<string, mixed>)|null */
    private readonly ?Closure $metadataProvider;

    /** @var Closure(string, string): void */
    private readonly Closure $manifestWriter;

    /**
     * @param array<string, mixed> $database Validated database configuration.
     * @param (callable(): DateTimeImmutable)|null $clock
     * @param (callable(int): string)|null $optionFileRandom
     * @param (callable(): array<string, mixed>)|null $metadataProvider
     * @param (callable(string, string): void)|null $manifestWriter
     */
    public function __construct(
        private readonly DatabaseBackupRoot $root,
        array $database,
        private readonly DatabaseBackupFilenameGenerator $filenameGenerator,
        private readonly DatabaseNativeClientResolver $clientResolver,
        private readonly DatabaseNativeClientCommandBuilder $commandBuilder,
        private readonly NativeProcessRunner $processRunner,
        ?callable $clock = null,
        ?callable $optionFileRandom = null,
        ?callable $metadataProvider = null,
        ?callable $manifestWriter = null,
    ) {
        $this->database = $database;
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        $this->optionFileRandom = $optionFileRandom === null ? null : Closure::fromCallable($optionFileRandom);
        $this->metadataProvider = $metadataProvider === null ? null : Closure::fromCallable($metadataProvider);
        $this->manifestWriter = Closure::fromCallable($manifestWriter ?? self::writeManifest(...));
    }

    public function create(): DatabaseBackupResult
    {
        $optionFile = null;
        $temporarySql = null;
        $temporaryManifest = null;
        $finalSql = null;
        $finalManifest = null;
        $sqlFinalized = false;
        $manifestFinalized = false;
        $completed = false;
        $metadataBefore = null;

        try {
            $client = $this->resolveClient();

            try {
                $createdAt = ($this->clock)();
                if (!$createdAt instanceof DateTimeImmutable) {
                    throw new RuntimeException();
                }

                $createdAtUtc = $createdAt
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z');
                $sqlBasename = $this->filenameGenerator->generate($createdAt);
                $manifestBasename = substr($sqlBasename, 0, -4) . '.manifest.json';
                $finalSql = $this->root->resolve($sqlBasename);
                $finalManifest = $this->root->resolve($manifestBasename);
                $temporarySql = $this->root->resolve($sqlBasename . '.part');
                $temporaryManifest = $this->root->resolve($manifestBasename . '.part');
                $metadataBefore = $this->optionalMetadata();

                foreach ([$finalSql, $finalManifest, $temporarySql, $temporaryManifest] as $path) {
                    if (file_exists($path) || is_link($path)) {
                        throw new RuntimeException();
                    }
                }

                $optionFile = new DatabaseClientOptionFile(
                    $this->root,
                    $this->database,
                    $this->optionFileRandom,
                );
                $command = $this->commandBuilder->build(
                    'backup',
                    $client['family'],
                    $client['executable'],
                    $optionFile->path(),
                    $this->databaseName(),
                    $this->databaseCharset(),
                );

                if (
                    $command['executable'] !== $client['executable']
                    || $command['family'] !== $client['family']
                    || !isset($command['arguments'][0])
                    || $command['arguments'][0] !== '--defaults-extra-file=' . $optionFile->path()
                ) {
                    throw new RuntimeException();
                }

                $request = new NativeProcessRequest(
                    $command['executable'],
                    $command['arguments'],
                    null,
                    $temporarySql,
                );
            } catch (Throwable) {
                throw new RuntimeException('Database backup could not be created.');
            }

            try {
                $processResult = $this->processRunner->run($request);
            } catch (Throwable) {
                throw new RuntimeException('Database backup could not be created.');
            }

            if ($processResult->exitCode !== 0) {
                throw new RuntimeException('Database backup could not be created.');
            }

            if (!self::isValidSqlOutput($temporarySql)) {
                throw new RuntimeException('Database backup output is invalid.');
            }

            try {
                $this->root->protectFile($temporarySql);
                $bytes = filesize($temporarySql);
                $sha256 = hash_file('sha256', $temporarySql);

                if (!is_int($bytes) || $bytes <= 0 || !is_string($sha256)) {
                    throw new RuntimeException();
                }

                $metadata = $this->optionalMetadata();
                if ($metadataBefore !== $metadata) {
                    throw new RuntimeException();
                }
                $toolVersion = self::boundedToolVersion($client['version']);
                $manifest = new DatabaseBackupManifest(
                    $createdAtUtc,
                    $sqlBasename,
                    $sha256,
                    $bytes,
                    $this->databaseEngine(),
                    $client['family'],
                    $toolVersion,
                    $metadata['application_revision'],
                    $metadata['migration_count'],
                    $metadata['latest_migration'],
                );
                $result = new DatabaseBackupResult(
                    $sqlBasename,
                    $manifestBasename,
                    $bytes,
                    $sha256,
                    $createdAtUtc,
                    $client['family'],
                    $toolVersion,
                );

                ($this->manifestWriter)($temporaryManifest, $manifest->toJson());
                $this->root->protectFile($temporaryManifest);

                if (file_exists($finalSql) || is_link($finalSql) || !@rename($temporarySql, $finalSql)) {
                    throw new RuntimeException();
                }
                $sqlFinalized = true;

                if (file_exists($finalManifest) || is_link($finalManifest) || !@rename($temporaryManifest, $finalManifest)) {
                    throw new RuntimeException();
                }
                $manifestFinalized = true;
            } catch (Throwable) {
                throw new RuntimeException('Database backup could not be finalized.');
            }

            $completed = true;

            return $result;
        } finally {
            if ($optionFile instanceof DatabaseClientOptionFile) {
                $optionFile->cleanup();
            }

            self::removeFile($temporarySql);
            self::removeFile($temporaryManifest);

            if (!$completed) {
                if ($manifestFinalized) {
                    self::removeFile($finalManifest);
                }
                if ($sqlFinalized) {
                    self::removeFile($finalSql);
                }
            }
        }
    }

    /** @return array{executable: string, family: string, version: string|null} */
    private function resolveClient(): array
    {
        try {
            $client = $this->clientResolver->resolve();
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

            return $client;
        } catch (Throwable) {
            throw new RuntimeException('Database backup client is unavailable.');
        }
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

    /** @return array{application_revision: string|null, migration_count: int|null, latest_migration: string|null} */
    private function optionalMetadata(): array
    {
        if ($this->metadataProvider === null) {
            return [
                'application_revision' => null,
                'migration_count' => null,
                'latest_migration' => null,
            ];
        }

        try {
            $metadata = ($this->metadataProvider)();
            if (!is_array($metadata)) {
                throw new RuntimeException();
            }

            $applicationRevision = $metadata['application_revision'] ?? null;
            $migrationCount = $metadata['migration_count'] ?? null;
            $latestMigration = $metadata['latest_migration'] ?? null;

            if (
                ($applicationRevision !== null && (
                    !is_string($applicationRevision)
                    || preg_match('/^[a-f0-9]{7,64}$/D', $applicationRevision) !== 1
                ))
                || !is_int($migrationCount)
                || $migrationCount <= 0
                || !is_string($latestMigration)
                || preg_match('/^[0-9]{14}_[a-z0-9_]+$/D', $latestMigration) !== 1
            ) {
                throw new RuntimeException();
            }

            return [
                'application_revision' => $applicationRevision,
                'migration_count' => $migrationCount,
                'latest_migration' => $latestMigration,
            ];
        } catch (Throwable) {
            throw new RuntimeException('Database backup metadata could not be captured.');
        }
    }

    private static function boundedToolVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = trim(str_replace(["\0", "\r", "\n"], ' ', $version));

        return $version === '' ? null : substr($version, 0, 80);
    }

    private static function isValidSqlOutput(string $path): bool
    {
        if (!is_file($path) || is_link($path)) {
            return false;
        }

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

        if (!is_string($prefix) || trim($prefix) === '') {
            return false;
        }

        return preg_match(
            '/(?:CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE|(?:^|[;\r\n])\s*SET\s+|MySQL dump|MariaDB dump)/i',
            $prefix,
        ) === 1;
    }

    private static function writeManifest(string $path, string $json): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException();
        }

        try {
            $remaining = $json;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new RuntimeException();
                }
                $remaining = substr($remaining, $written);
            }

            if (!fflush($handle)) {
                throw new RuntimeException();
            }
        } finally {
            fclose($handle);
        }
    }

    private static function removeFile(?string $path): void
    {
        if ($path !== null && (is_file($path) || is_link($path))) {
            @unlink($path);
        }
    }
}
