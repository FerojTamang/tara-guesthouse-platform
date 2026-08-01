<?php

declare(strict_types=1);

use App\Cli\RestoreDatabaseCommand;
use App\Core\Config;
use App\Core\ConnectionFactory;
use App\Core\Environment;
use App\Repositories\PdoDatabaseRestorePreflightRepository;
use App\Services\DatabaseBackupRoot;
use App\Services\DatabaseNativeClientCommandBuilder;
use App\Services\DatabaseNativeClientResolver;
use App\Services\DatabaseRestoreService;
use App\Services\ProcOpenNativeProcessRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Database restore command unavailable.\n");
    exit(1);
}

require $autoloadPath;

$loaded = null;
$loadConfiguration = static function () use (&$loaded, $basePath): array {
    if (is_array($loaded)) {
        return $loaded;
    }

    $environment = Environment::load($basePath);
    $environmentName = $environment->literalString('APP_ENV', 'local');
    if (!in_array($environmentName, ['local', 'testing', 'staging', 'production'], true)) {
        throw new RuntimeException('Database restore command failed.');
    }

    return $loaded = [
        'environment' => $environmentName,
        'database' => Config::loadDatabase($basePath . DIRECTORY_SEPARATOR . 'config', $environment),
    ];
};

$command = new RestoreDatabaseCommand(
    static function () use ($loadConfiguration): array {
        $loaded = $loadConfiguration();

        return [
            'database' => $loaded['database']['database'],
            'environment' => $loaded['environment'],
        ];
    },
    static function () use ($loadConfiguration, $basePath): DatabaseRestoreService {
        $loaded = $loadConfiguration();
        $database = $loaded['database'];
        $runner = new ProcOpenNativeProcessRunner();
        $connection = (new ConnectionFactory($database))->create();

        return new DatabaseRestoreService(
            new DatabaseBackupRoot(
                $basePath . DIRECTORY_SEPARATOR . 'storage',
                $basePath . DIRECTORY_SEPARATOR . 'public',
            ),
            $database,
            new PdoDatabaseRestorePreflightRepository($connection),
            new DatabaseNativeClientResolver($runner),
            new DatabaseNativeClientCommandBuilder(),
            $runner,
        );
    },
);

$result = $command->execute(array_slice($argv, 1));

if ($result['stdout'] !== '') {
    fwrite(STDOUT, $result['stdout']);
}
if ($result['stderr'] !== '') {
    fwrite(STDERR, $result['stderr']);
}

exit($result['exit']);
