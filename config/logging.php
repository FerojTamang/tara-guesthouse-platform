<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use Monolog\Level;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('Logging configuration requires an Environment instance.');
}

$channel = strtolower($environment->string('LOG_CHANNEL', 'file'));

if ($channel !== 'file') {
    throw new ConfigurationException('Only file logging is supported during S0-002.');
}

$relativePath = str_replace('\\', '/', $environment->string('LOG_PATH', 'storage/logs/application.log'));
$pathSegments = explode('/', $relativePath);

if (
    preg_match('/^(?:[A-Za-z]:|\/)/', $relativePath) === 1
    || in_array('', $pathSegments, true)
    || in_array('.', $pathSegments, true)
    || in_array('..', $pathSegments, true)
    || count($pathSegments) < 3
    || $pathSegments[0] !== 'storage'
    || $pathSegments[1] !== 'logs'
) {
    throw new ConfigurationException('LOG_PATH must resolve inside storage/logs/.');
}

$levelName = strtoupper($environment->string('LOG_LEVEL', 'debug'));

try {
    $level = Level::fromName($levelName);
} catch (Throwable $exception) {
    throw new ConfigurationException('LOG_LEVEL is not a valid Monolog level.', previous: $exception);
}

return [
    'channel' => $channel,
    'level' => $level,
    'path' => BASE_PATH . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pathSegments),
];
