<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ConfigurationException;
use App\Exceptions\DatabaseException;
use PDO;
use PDOException;

/**
 * Creates hardened PDO connections from validated database configuration.
 */
final class ConnectionFactory
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private readonly array $configuration)
    {
        $requiredKeys = [
            'driver',
            'host',
            'port',
            'database',
            'username',
            'password',
            'charset',
            'collation',
            'timezone',
            'connect_timeout',
            'ssl_ca',
            'options',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $configuration)) {
                throw new ConfigurationException(sprintf('Required database configuration key %s is missing.', $key));
            }
        }

        if (
            $configuration['driver'] !== 'mysql'
            || !is_string($configuration['host'])
            || $configuration['host'] === ''
            || preg_match('/^[A-Za-z0-9._:\-\[\]]+$/D', $configuration['host']) !== 1
            || !is_int($configuration['port'])
            || $configuration['port'] < 1
            || $configuration['port'] > 65535
            || !is_string($configuration['database'])
            || $configuration['database'] === ''
            || $configuration['database'] !== trim($configuration['database'])
            || preg_match('/[\x00-\x1F\x7F;=]/', $configuration['database']) === 1
            || !is_string($configuration['username'])
            || $configuration['username'] === ''
            || $configuration['username'] !== trim($configuration['username'])
            || preg_match('/[\x00-\x1F\x7F;=]/', $configuration['username']) === 1
            || !is_string($configuration['password'])
            || $configuration['charset'] !== 'utf8mb4'
            || $configuration['collation'] !== 'utf8mb4_unicode_ci'
            || $configuration['timezone'] !== '+00:00'
            || $configuration['connect_timeout'] !== 5
            || ($configuration['ssl_ca'] !== null && !is_string($configuration['ssl_ca']))
            || !is_array($configuration['options'])
        ) {
            throw new ConfigurationException('The database connection configuration is invalid.');
        }

        $expectedOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ];

        if ($configuration['ssl_ca'] !== null) {
            if (!is_file($configuration['ssl_ca']) || !is_readable($configuration['ssl_ca'])) {
                throw new ConfigurationException('The database connection configuration is invalid.');
            }

            $expectedOptions[PDO::MYSQL_ATTR_SSL_CA] = $configuration['ssl_ca'];
        }

        if ($configuration['options'] !== $expectedOptions) {
            throw new ConfigurationException('The database connection configuration is invalid.');
        }
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->configuration['host'],
            $this->configuration['port'],
            $this->configuration['database'],
            $this->configuration['charset'],
        );
    }

    public function create(): PDO
    {
        try {
            $connection = new PDO(
                $this->dsn(),
                $this->configuration['username'],
                $this->configuration['password'],
                $this->configuration['options'],
            );

            $connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            $connection->exec("SET SESSION time_zone = '+00:00'");

            return $connection;
        } catch (PDOException) {
            throw new DatabaseException('Database connection failed.');
        }
    }
}
