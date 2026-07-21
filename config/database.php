<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('Database configuration requires an Environment instance.');
}

$environmentName = $environment->literalString('APP_ENV', 'local');

if (!in_array($environmentName, ['local', 'testing', 'staging', 'production'], true)) {
    throw new ConfigurationException('APP_ENV must be local, testing, staging, or production.');
}

$prefix = $environmentName === 'testing' ? 'TEST_DB_' : 'DB_';
$literal = static function (string $name, ?string $default = null) use ($environment, $prefix): string {
    return $environment->literalString($prefix . $name, $default);
};

$driver = $literal('DRIVER', 'mysql');

if ($driver !== 'mysql') {
    throw new ConfigurationException('The database driver must be mysql.');
}

$host = $literal('HOST');

if ($host === '' || preg_match('/^[A-Za-z0-9._:\-\[\]]+$/D', $host) !== 1) {
    throw new ConfigurationException('The database host contains prohibited characters.');
}

$portValue = $literal('PORT');

if (preg_match('/^[1-9][0-9]{0,4}$/D', $portValue) !== 1) {
    throw new ConfigurationException('The database port must be an integer from 1 to 65535.');
}

$port = (int) $portValue;

if ($port > 65535) {
    throw new ConfigurationException('The database port must be an integer from 1 to 65535.');
}

$database = $literal('DATABASE');

if ($database === '' || $database !== trim($database) || preg_match('/[\x00-\x1F\x7F;=]/', $database) === 1) {
    throw new ConfigurationException('The database name must be non-empty and contain no prohibited characters.');
}

$username = $literal('USERNAME');

if ($username === '' || $username !== trim($username) || preg_match('/[\x00-\x1F\x7F;=]/', $username) === 1) {
    throw new ConfigurationException('The database username must be non-empty and contain no prohibited characters.');
}

$password = $literal('PASSWORD');

if (in_array($environmentName, ['staging', 'production'], true) && $password === '') {
    throw new ConfigurationException('The database password is required in staging and production.');
}

$charset = $literal('CHARSET', 'utf8mb4');

if ($charset !== 'utf8mb4') {
    throw new ConfigurationException('The database charset must be utf8mb4.');
}

$collation = $literal('COLLATION', 'utf8mb4_unicode_ci');

if ($collation !== 'utf8mb4_unicode_ci') {
    throw new ConfigurationException('The database collation must be utf8mb4_unicode_ci.');
}

$timezone = $literal('TIMEZONE', '+00:00');

if ($timezone !== '+00:00') {
    throw new ConfigurationException('The database connection timezone must be +00:00.');
}

$timeoutValue = $literal('CONNECT_TIMEOUT', '5');

if ($timeoutValue !== '5') {
    throw new ConfigurationException('The database connection timeout must be 5 seconds.');
}

$sslCaValue = $literal('SSL_CA', '');
$sslCa = null;

if ($sslCaValue !== '') {
    if ($sslCaValue !== trim($sslCaValue)) {
        throw new ConfigurationException('The database SSL CA path must not contain surrounding whitespace.');
    }

    $sslCa = realpath($sslCaValue);

    if ($sslCa === false || !is_file($sslCa) || !is_readable($sslCa)) {
        throw new ConfigurationException('The database SSL CA path must identify an existing readable file.');
    }
}

if ($environmentName === 'testing') {
    if (preg_match('/_(?:test|testing)$/D', $database) !== 1) {
        throw new ConfigurationException('The testing database name must end in _test or _testing.');
    }

    $developmentDatabase = $environment->literalString('DB_DATABASE');

    if (
        $developmentDatabase === ''
        || $developmentDatabase !== trim($developmentDatabase)
        || preg_match('/[\x00-\x1F\x7F;=]/', $developmentDatabase) === 1
    ) {
        throw new ConfigurationException('The development database name must be valid when testing.');
    }

    if ($developmentDatabase === $database) {
        throw new ConfigurationException('The testing database must differ from the development database.');
    }
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::ATTR_TIMEOUT => 5,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
];

if ($sslCa !== null) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
}

return [
    'driver' => $driver,
    'host' => $host,
    'port' => $port,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset' => $charset,
    'collation' => $collation,
    'timezone' => $timezone,
    'connect_timeout' => 5,
    'ssl_ca' => $sslCa,
    'options' => $options,
];
