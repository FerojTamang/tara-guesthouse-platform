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

$timezone = $environment->string('APP_TIMEZONE');

try {
    $validatedTimezone = new DateTimeZone($timezone);
} catch (Exception $exception) {
    throw new ConfigurationException('APP_TIMEZONE must be a valid PHP timezone.', previous: $exception);
}

$debugRequested = $environment->bool('APP_DEBUG', false);

return [
    'name' => $environment->string('APP_NAME'),
    'environment' => $environmentName,
    'debug' => $environmentName === 'local' && $debugRequested,
    'url' => $environment->nullableString('APP_URL'),
    'timezone' => $validatedTimezone->getName(),
];
