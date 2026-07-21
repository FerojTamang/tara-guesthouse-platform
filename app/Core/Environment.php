<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ConfigurationException;
use Dotenv\Dotenv;

/**
 * Provides immutable, strictly typed access to environment values.
 */
final class Environment
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * Loads an optional .env file without replacing host environment values.
     */
    public static function load(string $basePath): self
    {
        $resolvedBasePath = realpath($basePath);

        if ($resolvedBasePath === false || !is_dir($resolvedBasePath)) {
            throw new ConfigurationException('The application base path is invalid.');
        }

        $processValues = getenv();
        $serverValues = $_SERVER;
        $environmentValues = $_ENV;

        Dotenv::createImmutable($resolvedBasePath)->safeLoad();

        $values = array_merge(
            $_SERVER,
            $_ENV,
            $serverValues,
            $environmentValues,
            is_array($processValues) ? $processValues : [],
        );

        return new self($values);
    }

    /**
     * Creates a test-friendly environment from explicit values.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * Determines whether a key exists, including keys with empty values.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Returns a required non-empty string or an explicit default.
     */
    public function string(string $key, ?string $default = null): string
    {
        if (!$this->has($key)) {
            if ($default !== null) {
                return $default;
            }

            throw new ConfigurationException(sprintf('Required environment variable %s is missing.', $key));
        }

        $value = $this->values[$key];

        if (!is_string($value)) {
            throw new ConfigurationException(sprintf('Environment variable %s must be a string.', $key));
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            if ($default !== null) {
                return $default;
            }

            throw new ConfigurationException(sprintf('Required environment variable %s cannot be empty.', $key));
        }

        return $stringValue;
    }

    /**
     * Returns null for missing or empty values.
     */
    public function nullableString(string $key): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = $this->values[$key];

        if (!is_string($value)) {
            throw new ConfigurationException(sprintf('Environment variable %s must be a string.', $key));
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    /**
     * Parses an explicit conventional boolean value.
     */
    public function bool(string $key, ?bool $default = null): bool
    {
        if (!$this->has($key) || $this->values[$key] === '') {
            if ($default !== null) {
                return $default;
            }

            throw new ConfigurationException(sprintf('Required environment variable %s is missing.', $key));
        }

        $value = filter_var($this->values[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            throw new ConfigurationException(sprintf('Environment variable %s must be a valid boolean.', $key));
        }

        return $value;
    }

    /**
     * Parses a base-10 integer without loose conversion.
     */
    public function int(string $key, ?int $default = null): int
    {
        if (!$this->has($key) || $this->values[$key] === '') {
            if ($default !== null) {
                return $default;
            }

            throw new ConfigurationException(sprintf('Required environment variable %s is missing.', $key));
        }

        $value = filter_var($this->values[$key], FILTER_VALIDATE_INT);

        if ($value === false) {
            throw new ConfigurationException(sprintf('Environment variable %s must be a valid integer.', $key));
        }

        return $value;
    }
}
