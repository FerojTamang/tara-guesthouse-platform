<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('Application configuration requires an Environment instance.');
}

$environmentName = strtolower($environment->string('APP_ENV'));
$approvedEnvironments = ['local', 'testing', 'staging', 'production'];

if (!in_array($environmentName, $approvedEnvironments, true)) {
    throw new ConfigurationException('APP_ENV must be local, testing, staging, or production.');
}

$protectedEnvironment = in_array($environmentName, ['staging', 'production'], true);
$timezone = $environment->string('APP_TIMEZONE');

try {
    $validatedTimezone = new DateTimeZone($timezone);
} catch (Exception $exception) {
    throw new ConfigurationException('APP_TIMEZONE must be a valid PHP timezone.', previous: $exception);
}

$applicationUrl = $environment->nullableString('APP_URL');

if ($applicationUrl !== null) {
    if (
        $applicationUrl !== trim($applicationUrl)
        || filter_var($applicationUrl, FILTER_VALIDATE_URL) === false
    ) {
        throw new ConfigurationException('APP_URL must be a valid absolute HTTP or HTTPS origin.');
    }

    $parts = parse_url($applicationUrl);
    $scheme = is_array($parts) && is_string($parts['scheme'] ?? null)
        ? strtolower($parts['scheme'])
        : null;
    $host = is_array($parts) && is_string($parts['host'] ?? null)
        ? $parts['host']
        : null;
    $path = is_array($parts) && is_string($parts['path'] ?? null)
        ? $parts['path']
        : '';
    $port = is_array($parts) ? ($parts['port'] ?? null) : null;

    if (
        !is_array($parts)
        || !in_array($scheme, ['http', 'https'], true)
        || $host === null
        || $host === ''
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
        || !in_array($path, ['', '/'], true)
        || ($port !== null && (!is_int($port) || $port < 1 || $port > 65535))
    ) {
        throw new ConfigurationException('APP_URL must be a plain application origin without credentials, path, query, or fragment.');
    }

    if ($protectedEnvironment && $scheme !== 'https') {
        throw new ConfigurationException('APP_URL must use HTTPS for staging and production.');
    }
} elseif ($protectedEnvironment) {
    throw new ConfigurationException('APP_URL must be configured for staging and production.');
}

$debugRequested = $environment->bool('APP_DEBUG', false);

return [
    'name' => $environment->string('APP_NAME'),
    'environment' => $environmentName,
    'debug' => $environmentName === 'local' && $debugRequested,
    'url' => $applicationUrl,
    'timezone' => $validatedTimezone->getName(),
];
