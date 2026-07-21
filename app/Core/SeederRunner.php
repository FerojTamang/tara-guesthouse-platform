<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\DatabaseException;
use DirectoryIterator;
use PDO;
use Throwable;

/**
 * Discovers and executes explicitly grouped seeders in lexical order.
 */
final class SeederRunner
{
    private const GROUPS = ['production', 'development', 'testing', 'demo'];

    private readonly PDO $connection;

    private readonly string $seederPath;

    public function __construct(
        PDO $connection,
        string $seederPath,
    ) {
        $resolvedPath = realpath($seederPath);

        if (
            $resolvedPath === false
            || !is_dir($resolvedPath)
            || !is_readable($resolvedPath)
            || is_link($seederPath)
        ) {
            throw new DatabaseException('The seeder directory is unavailable.');
        }

        $this->connection = $connection;
        $this->seederPath = $resolvedPath;
    }

    /**
     * @return array<string, array{seeder: Seeder, group: string, path: string}>
     */
    public function discover(): array
    {
        $seeders = [];

        foreach (new DirectoryIterator($this->seederPath) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->isLink()) {
                throw new DatabaseException('Seeder symlinks are not allowed.');
            }

            $filename = $file->getFilename();

            if (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*\.php$/D', $filename) !== 1) {
                throw new DatabaseException('A seeder filename is invalid.');
            }

            $identifier = substr($filename, 0, -4);

            if (isset($seeders[$identifier])) {
                throw new DatabaseException('A duplicate seeder identifier was found.');
            }

            $path = realpath($file->getPathname());

            if ($path === false || !$this->isDirectFile($path)) {
                throw new DatabaseException('A seeder path escapes the seeder directory.');
            }

            try {
                $seeder = $this->loadSeeder($path);
            } catch (Throwable) {
                throw new DatabaseException('A seeder file could not be loaded.');
            }

            if (!$seeder instanceof Seeder) {
                throw new DatabaseException('Each seeder file must return one Seeder instance.');
            }

            $group = $seeder->group();

            if (!in_array($group, self::GROUPS, true)) {
                throw new DatabaseException('A seeder has an invalid group.');
            }

            $seeders[$identifier] = [
                'seeder' => $seeder,
                'group' => $group,
                'path' => $path,
            ];
        }

        ksort($seeders, SORT_STRING);

        return $seeders;
    }

    /**
     * @return list<string>
     */
    public function run(string $group, string $environment): array
    {
        if (!in_array($group, self::GROUPS, true)) {
            throw new DatabaseException('The requested seeder group is invalid.');
        }

        if (!in_array($environment, ['local', 'testing', 'staging', 'production'], true)) {
            throw new DatabaseException('The seeder environment is invalid.');
        }

        if ($environment === 'production' && $group !== 'production') {
            throw new DatabaseException('Non-production seeders are refused in production.');
        }

        if ($this->connection->inTransaction()) {
            throw new DatabaseException('Seeders cannot run inside an existing transaction.');
        }

        $executed = [];

        foreach ($this->discover() as $identifier => $details) {
            if ($details['group'] !== $group) {
                continue;
            }

            $this->execute($details['seeder']);
            $executed[] = $identifier;
        }

        return $executed;
    }

    private function execute(Seeder $seeder): void
    {
        $transactional = $seeder->transactional();
        $startedTransaction = false;

        try {
            if ($transactional) {
                $this->connection->beginTransaction();
                $startedTransaction = true;
            }

            $seeder->run($this->connection);

            if ($startedTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new DatabaseException('A seeder could not be executed.');
        }
    }

    private function isDirectFile(string $path): bool
    {
        $expectedDirectory = $this->seederPath;
        $actualDirectory = dirname($path);

        if (DIRECTORY_SEPARATOR === '\\') {
            return strtolower($actualDirectory) === strtolower($expectedDirectory);
        }

        return $actualDirectory === $expectedDirectory;
    }

    private function loadSeeder(string $path): mixed
    {
        $bufferLevel = ob_get_level();
        ob_start(static fn (string $output): string => '');

        try {
            return (static fn (string $seederFile): mixed => require $seederFile)($path);
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }
}
