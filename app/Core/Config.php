<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ConfigurationException;

/**
 * Stores validated application configuration using dot-notation lookup.
 */
final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    private function __construct(private readonly array $items)
    {
    }

    /**
     * Loads the configuration groups approved through S0-004.
     */
    public static function load(string $configPath, Environment $environment): self
    {
        $resolvedConfigPath = realpath($configPath);

        if ($resolvedConfigPath === false || !is_dir($resolvedConfigPath)) {
            throw new ConfigurationException('The configuration directory is invalid.');
        }

        return new self([
            'app' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'app.php', $environment),
            'database' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'database.php', $environment),
            'logging' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'logging.php', $environment),
            'session' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'session.php', $environment),
        ]);
    }

    /**
     * Loads only strict database configuration for CLI commands.
     *
     * @return array<string, mixed>
     */
    public static function loadDatabase(string $configPath, Environment $environment): array
    {
        $resolvedConfigPath = realpath($configPath);

        if ($resolvedConfigPath === false || !is_dir($resolvedConfigPath)) {
            throw new ConfigurationException('The configuration directory is invalid.');
        }

        return self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'database.php', $environment);
    }

    /**
     * Creates configuration from explicit values for isolated tests.
     *
     * @param array<string, mixed> $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    /**
     * Returns a value using dot notation or the supplied default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Returns a required configuration value.
     */
    public function require(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new ConfigurationException(sprintf('Required configuration key %s is missing.', $key));
        }

        return $this->get($key);
    }

    /**
     * Determines whether a dot-notation key exists.
     */
    public function has(string $key): bool
    {
        $marker = new \stdClass();

        return $this->get($key, $marker) !== $marker;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadFile(string $file, Environment $environment): array
    {
        if (!is_file($file)) {
            throw new ConfigurationException(sprintf('Required configuration file %s is missing.', basename($file)));
        }

        $configuration = require $file;

        if (!is_array($configuration)) {
            throw new ConfigurationException(sprintf('Configuration file %s must return an array.', basename($file)));
        }

        return $configuration;
    }
}
