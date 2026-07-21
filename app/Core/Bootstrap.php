<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ConfigurationException;

/**
 * Builds the minimal S0-002 application runtime.
 */
final class Bootstrap
{
    private const ROUTE_FILES = ['web.php', 'admin.php', 'api.php'];

    /**
     * Boots the application from one validated repository root.
     */
    public static function boot(string $basePath): Application
    {
        $resolvedBasePath = realpath($basePath);

        if ($resolvedBasePath === false || !is_dir($resolvedBasePath)) {
            throw new ConfigurationException('The supplied application base path is invalid.');
        }

        if (!is_file($resolvedBasePath . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new ConfigurationException('The supplied path is not a valid application repository.');
        }

        foreach (['app', 'config', 'routes', 'public', 'storage'] as $requiredDirectory) {
            if (!is_dir($resolvedBasePath . DIRECTORY_SEPARATOR . $requiredDirectory)) {
                throw new ConfigurationException('The supplied path is not a valid application repository.');
            }
        }

        self::definePaths($resolvedBasePath);

        $environment = Environment::load(BASE_PATH);
        $config = Config::load(CONFIG_PATH, $environment);
        $timezone = $config->require('app.timezone');

        if (!is_string($timezone) || !date_default_timezone_set($timezone)) {
            throw new ConfigurationException('The configured application timezone is invalid.');
        }

        $logger = LoggerFactory::create($config);
        $errorHandler = new ErrorHandler($logger, $config->require('app.debug') === true);
        $errorHandler->register();

        $router = new Router();
        self::loadRoutes($router);

        return new Application($router, $errorHandler);
    }

    private static function definePaths(string $basePath): void
    {
        $paths = [
            'BASE_PATH' => $basePath,
            'APP_PATH' => $basePath . DIRECTORY_SEPARATOR . 'app',
            'CONFIG_PATH' => $basePath . DIRECTORY_SEPARATOR . 'config',
            'ROUTES_PATH' => $basePath . DIRECTORY_SEPARATOR . 'routes',
            'STORAGE_PATH' => $basePath . DIRECTORY_SEPARATOR . 'storage',
            'PUBLIC_PATH' => $basePath . DIRECTORY_SEPARATOR . 'public',
        ];

        foreach ($paths as $constant => $path) {
            if (defined($constant) && constant($constant) !== $path) {
                throw new ConfigurationException(sprintf('%s is already defined with another path.', $constant));
            }
        }

        foreach ($paths as $constant => $path) {
            if (!defined($constant) && !define($constant, $path)) {
                throw new ConfigurationException(sprintf('%s could not be defined.', $constant));
            }
        }
    }

    private static function loadRoutes(Router $router): void
    {
        foreach (self::ROUTE_FILES as $routeFile) {
            $path = ROUTES_PATH . DIRECTORY_SEPARATOR . $routeFile;

            if (!is_file($path)) {
                throw new ConfigurationException(sprintf('Required route file %s is missing.', $routeFile));
            }

            $registrar = require $path;

            if (!is_callable($registrar)) {
                throw new ConfigurationException(sprintf('Route file %s must return a callable registrar.', $routeFile));
            }

            $registrar($router);
        }
    }
}
