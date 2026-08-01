<?php

declare(strict_types=1);

use App\Cli\BackupDatabaseCommand;
use App\Core\Config;
use App\Core\Environment;
use App\Services\DatabaseBackupFilenameGenerator;
use App\Services\DatabaseBackupRoot;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseNativeClientCommandBuilder;
use App\Services\DatabaseNativeClientResolver;
use App\Services\ProcOpenNativeProcessRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Database backup command unavailable.\n");
    exit(1);
}

require $autoloadPath;

$command = new BackupDatabaseCommand(static function () use ($basePath) {
    $environment = Environment::load($basePath);
    $database = Config::loadDatabase($basePath . DIRECTORY_SEPARATOR . 'config', $environment);
    $runner = new ProcOpenNativeProcessRunner();
    $root = new DatabaseBackupRoot(
        $basePath . DIRECTORY_SEPARATOR . 'storage',
        $basePath . DIRECTORY_SEPARATOR . 'public',
    );

    return (new DatabaseBackupService(
        $root,
        $database,
        new DatabaseBackupFilenameGenerator(),
        new DatabaseNativeClientResolver($runner),
        new DatabaseNativeClientCommandBuilder(),
        $runner,
    ))->create();
});

$result = $command->execute(array_slice($argv, 1));

if ($result['stdout'] !== '') {
    fwrite(STDOUT, $result['stdout']);
}
if ($result['stderr'] !== '') {
    fwrite(STDERR, $result['stderr']);
}

exit($result['exit']);
