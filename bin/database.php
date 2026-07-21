<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ConnectionFactory;
use App\Core\Environment;
use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use App\Exceptions\DatabaseException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Database command unavailable.\n");
    exit(1);
}

require $autoloadPath;

$arguments = array_slice($argv, 1);
$command = array_shift($arguments);
$supportedCommands = ['status', 'migrate', 'rollback', 'seed'];

if (!is_string($command) || !in_array($command, $supportedCommands, true)) {
    fwrite(STDERR, "Unknown database command.\n");
    exit(2);
}

$force = false;
$group = null;
$forceSeen = false;
$groupSeen = false;

foreach ($arguments as $argument) {
    if ($argument === '--force') {
        if ($command !== 'migrate' || $forceSeen) {
            fwrite(STDERR, "Invalid or duplicate database command option.\n");
            exit(2);
        }

        $force = true;
        $forceSeen = true;
        continue;
    }

    if (str_starts_with($argument, '--group=')) {
        if ($command !== 'seed' || $groupSeen) {
            fwrite(STDERR, "Invalid or duplicate database command option.\n");
            exit(2);
        }

        $group = substr($argument, strlen('--group='));
        $groupSeen = true;
        continue;
    }

    fwrite(STDERR, "Unknown database command option.\n");
    exit(2);
}

try {
    $environment = Environment::load($basePath);
    $environmentName = $environment->literalString('APP_ENV', 'local');

    if ($command === 'migrate' && $environmentName === 'production' && !$force) {
        throw new DatabaseException('Production migration requires --force.');
    }

    if ($command === 'rollback' && $environmentName === 'production') {
        throw new DatabaseException('Production rollback is disabled.');
    }

    if ($command === 'seed') {
        if (!is_string($group) || $group === '') {
            throw new DatabaseException('The seed command requires --group=<group>.');
        }

        if ($environmentName === 'production' && in_array($group, ['development', 'testing', 'demo'], true)) {
            throw new DatabaseException('Non-production seeders are refused in production.');
        }
    }

    $configuration = Config::loadDatabase($basePath . DIRECTORY_SEPARATOR . 'config', $environment);
    $connection = (new ConnectionFactory($configuration))->create();
    $migrationRunner = new MigrationRunner(
        $connection,
        $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
    );

    if ($command === 'status') {
        $status = $migrationRunner->status();
        $applied = count(array_filter($status, static fn (array $row): bool => $row['status'] === 'applied'));
        $pending = count($status) - $applied;
        fwrite(STDOUT, sprintf("Applied migrations: %d\nPending migrations: %d\n", $applied, $pending));
        exit(0);
    }

    if ($command === 'migrate') {
        $migrated = $migrationRunner->migrate();
        fwrite(STDOUT, sprintf("Applied migrations: %d\n", count($migrated)));
        exit(0);
    }

    if ($command === 'rollback') {
        $rolledBack = $migrationRunner->rollbackLastBatch();
        fwrite(STDOUT, sprintf("Rolled back migrations: %d\n", count($rolledBack)));
        exit(0);
    }

    $seederRunner = new SeederRunner(
        $connection,
        $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders',
    );
    $seeded = $seederRunner->run($group, $environmentName);
    fwrite(STDOUT, sprintf("Executed seeders: %d\n", count($seeded)));
    exit(0);
} catch (DatabaseException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Database command failed.\n");
    exit(1);
}
