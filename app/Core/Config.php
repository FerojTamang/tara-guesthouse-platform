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
     * Loads the approved application configuration groups.
     */
    public static function load(string $configPath, Environment $environment): self
    {
        $resolvedConfigPath = realpath($configPath);

        if ($resolvedConfigPath === false || !is_dir($resolvedConfigPath)) {
            throw new ConfigurationException('The configuration directory is invalid.');
        }

        return new self([
            'app' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'app.php', $environment),
            'booking' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'booking.php', $environment),
            'database' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'database.php', $environment),
            'logging' => self::loadFile($resolvedConfigPath . DIRECTORY_SEPARATOR . 'logging.php', $environment),
            'security' => self::loadSecurity($resolvedConfigPath, $environment),
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
     * Loads and validates the tracked non-secret security policy.
     *
     * @return array<string, mixed>
     */
    public static function loadSecurity(string $configPath, Environment $environment): array
    {
        $resolvedConfigPath = realpath($configPath);

        if ($resolvedConfigPath === false || !is_dir($resolvedConfigPath)) {
            throw new ConfigurationException('The configuration directory is invalid.');
        }

        $configuration = self::loadFile(
            $resolvedConfigPath . DIRECTORY_SEPARATOR . 'security.php',
            $environment,
        );

        return self::validateSecurity($configuration);
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

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private static function validateSecurity(array $configuration): array
    {
        if (
            array_keys($configuration) !== [
                'administrator_bootstrap',
                'administrator_login',
                'public_booking',
            ]
        ) {
            throw new ConfigurationException('Security configuration is invalid.');
        }

        self::validateAdministratorBootstrapPolicy($configuration['administrator_bootstrap']);
        self::validateAdministratorLoginPolicy($configuration['administrator_login']);
        self::validatePublicBookingPolicy($configuration['public_booking']);

        return $configuration;
    }

    private static function validateAdministratorBootstrapPolicy(mixed $policy): void
    {
        $requiredKeys = [
            'allowed_environments',
            'force_required_environments',
            'full_name_min_characters',
            'full_name_max_characters',
            'username_min_characters',
            'username_max_characters',
            'email_max_bytes',
            'phone_max_characters',
            'password_min_characters',
            'password_max_bytes',
        ];

        if (!is_array($policy) || array_keys($policy) !== $requiredKeys) {
            throw new ConfigurationException('Security configuration is invalid.');
        }

        $supportedEnvironments = ['local', 'testing', 'staging', 'production'];
        $allowed = $policy['allowed_environments'];
        $forceRequired = $policy['force_required_environments'];

        if (
            !is_array($allowed)
            || !is_array($forceRequired)
            || $allowed === []
            || count($allowed) !== count(array_unique($allowed, SORT_REGULAR))
            || count($forceRequired) !== count(array_unique($forceRequired, SORT_REGULAR))
        ) {
            throw new ConfigurationException('Security configuration is invalid.');
        }

        foreach (array_merge($allowed, $forceRequired) as $environment) {
            if (!is_string($environment) || !in_array($environment, $supportedEnvironments, true)) {
                throw new ConfigurationException('Security configuration is invalid.');
            }
        }

        if (array_diff($forceRequired, $allowed) !== []) {
            throw new ConfigurationException('Security configuration is invalid.');
        }

        foreach (array_slice($requiredKeys, 2) as $key) {
            if (!is_int($policy[$key]) || $policy[$key] <= 0) {
                throw new ConfigurationException('Security configuration is invalid.');
            }
        }

        if (
            $policy['full_name_min_characters'] > $policy['full_name_max_characters']
            || $policy['username_min_characters'] > $policy['username_max_characters']
            || $policy['full_name_max_characters'] > 100
            || $policy['username_max_characters'] > 50
            || $policy['email_max_bytes'] > 150
            || $policy['phone_max_characters'] > 20
            || $policy['password_max_bytes'] > 72
        ) {
            throw new ConfigurationException('Security configuration is invalid.');
        }
    }

    private static function validateAdministratorLoginPolicy(mixed $policy): void
    {
        $requiredKeys = [
            'username_min_characters',
            'username_max_characters',
            'identifier_max_bytes',
            'password_max_bytes',
            'max_failed_attempts',
            'attempt_window_seconds',
            'block_duration_seconds',
        ];

        if (!is_array($policy) || array_keys($policy) !== $requiredKeys) {
            throw new ConfigurationException('Security configuration is invalid.');
        }

        foreach ($requiredKeys as $key) {
            if (!is_int($policy[$key]) || $policy[$key] <= 0) {
                throw new ConfigurationException('Security configuration is invalid.');
            }
        }

        if (
            $policy['username_min_characters'] > $policy['username_max_characters']
            || $policy['username_max_characters'] > 50
            || $policy['identifier_max_bytes'] > 150
            || $policy['password_max_bytes'] > 72
            || $policy['max_failed_attempts'] > 20
            || $policy['attempt_window_seconds'] > 86400
            || $policy['block_duration_seconds'] > 86400
        ) {
            throw new ConfigurationException('Security configuration is invalid.');
        }
    }


    private static function validatePublicBookingPolicy(mixed $policy): void
    {
        $requiredKeys = [
            'max_attempts',
            'attempt_window_seconds',
            'block_duration_seconds',
        ];

        if (!is_array($policy) || array_keys($policy) !== $requiredKeys) {
            throw new ConfigurationException('Security configuration is invalid.');
        }

        foreach ($requiredKeys as $key) {
            if (!is_int($policy[$key]) || $policy[$key] <= 0) {
                throw new ConfigurationException('Security configuration is invalid.');
            }
        }

        if (
            $policy['max_attempts'] > 20
            || $policy['attempt_window_seconds'] > 86400
            || $policy['block_duration_seconds'] > 86400
        ) {
            throw new ConfigurationException('Security configuration is invalid.');
        }
    }


}
