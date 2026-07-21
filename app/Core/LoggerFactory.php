<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Creates the application logger with a safe system-error-log fallback.
 */
final class LoggerFactory
{
    /**
     * Creates a configured Monolog logger.
     */
    public static function create(Config $config): LoggerInterface
    {
        $channel = $config->require('logging.channel');
        $level = $config->require('logging.level');
        $path = $config->require('logging.path');

        if (!is_string($channel) || !is_string($path) || !$level instanceof Level) {
            throw new \InvalidArgumentException('Logging configuration has invalid types.');
        }

        $logger = new Logger($channel);

        try {
            self::prepareLogFile($path);
            $logger->pushHandler(new StreamHandler($path, $level));
        } catch (Throwable) {
            error_log('Application file logging is unavailable; using the PHP error log.');
            $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $level));
        }

        return $logger;
    }

    private static function prepareLogFile(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('The log directory could not be created.');
        }

        // The actual open is authoritative; is_writable() is unreliable on some Windows hosts.
        $handle = @fopen($path, 'ab');

        if ($handle === false) {
            throw new \RuntimeException('The log file cannot be opened.');
        }

        fclose($handle);
    }
}
