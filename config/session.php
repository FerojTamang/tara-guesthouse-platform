<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('Session configuration requires an Environment instance.');
}

if (!defined('STORAGE_PATH') || !defined('PUBLIC_PATH')) {
    throw new ConfigurationException('Session configuration requires canonical application paths.');
}

$environmentName = strtolower($environment->string('APP_ENV'));
$protectedEnvironment = in_array($environmentName, ['staging', 'production'], true);

$positiveInteger = static function (string $key, int $default) use ($environment, $protectedEnvironment): int {
    $rawValue = $environment->literalString($key, '');

    if ($rawValue === '') {
        if ($protectedEnvironment) {
            throw new ConfigurationException(sprintf('%s must be configured for staging and production.', $key));
        }

        return $default;
    }

    if ($rawValue !== trim($rawValue)) {
        throw new ConfigurationException(sprintf('%s must not contain surrounding whitespace.', $key));
    }

    $value = filter_var($rawValue, FILTER_VALIDATE_INT);

    if ($value === false || $value <= 0) {
        throw new ConfigurationException(sprintf('%s must be a positive integer.', $key));
    }

    return $value;
};

$strictBoolean = static function (string $key, bool $default) use ($environment): bool {
    $rawValue = $environment->literalString($key, '');

    if ($rawValue === '') {
        return $default;
    }

    if ($rawValue !== trim($rawValue)) {
        throw new ConfigurationException(sprintf('%s must not contain surrounding whitespace.', $key));
    }

    return $environment->bool($key);
};

$inactivityTimeout = $positiveInteger('SESSION_INACTIVITY_TIMEOUT', 1800);
$absoluteLifetime = $positiveInteger('SESSION_ABSOLUTE_LIFETIME', 43200);

if ($absoluteLifetime < $inactivityTimeout) {
    throw new ConfigurationException('SESSION_ABSOLUTE_LIFETIME cannot be shorter than SESSION_INACTIVITY_TIMEOUT.');
}

$name = $environment->literalString('SESSION_NAME', 'tgh_session');

if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name) !== 1) {
    throw new ConfigurationException('SESSION_NAME must be a safe cookie name beginning with a letter.');
}

$secureCookie = $strictBoolean('SESSION_SECURE_COOKIE', false);

if ($protectedEnvironment && !$secureCookie) {
    throw new ConfigurationException('SESSION_SECURE_COOKIE must be true for staging and production.');
}

$httpOnly = $strictBoolean('SESSION_HTTP_ONLY', true);

if (!$httpOnly) {
    throw new ConfigurationException('SESSION_HTTP_ONLY must be true.');
}

$sameSite = $environment->literalString('SESSION_SAME_SITE', 'Lax');

if (!in_array($sameSite, ['Lax', 'Strict'], true)) {
    throw new ConfigurationException('SESSION_SAME_SITE must be Lax or Strict.');
}

$cookiePath = $environment->literalString('SESSION_PATH', '/');

if (
    !str_starts_with($cookiePath, '/')
    || preg_match('/[\x00-\x20\x7F;,]/', $cookiePath) === 1
) {
    throw new ConfigurationException('SESSION_PATH must be an absolute cookie path without prohibited characters.');
}

$cookieDomainValue = $environment->literalString('SESSION_DOMAIN', '');
$cookieDomain = $cookieDomainValue === '' ? null : $cookieDomainValue;

if (
    $cookieDomain !== null
    && (
        strlen($cookieDomain) > 253
        || preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/D', $cookieDomain) !== 1
    )
) {
    throw new ConfigurationException('SESSION_DOMAIN must be blank or a valid plain hostname.');
}

$savePath = STORAGE_PATH . DIRECTORY_SEPARATOR . 'sessions';
$resolvedStoragePath = realpath(STORAGE_PATH);
$resolvedSavePath = realpath($savePath);
$resolvedPublicPath = realpath(PUBLIC_PATH);

if ($resolvedStoragePath === false || !is_dir($resolvedStoragePath)) {
    throw new ConfigurationException('The private storage directory is unavailable.');
}

if ($resolvedSavePath === false || !is_dir($resolvedSavePath)) {
    throw new ConfigurationException('The private session directory is unavailable.');
}

if ($resolvedPublicPath === false || !is_dir($resolvedPublicPath)) {
    throw new ConfigurationException('The public directory is unavailable.');
}

$caseInsensitivePaths = DIRECTORY_SEPARATOR === '\\';
$expectedSavePath = $resolvedStoragePath . DIRECTORY_SEPARATOR . 'sessions';
$comparableSavePath = $caseInsensitivePaths ? strtolower($resolvedSavePath) : $resolvedSavePath;
$comparableExpectedSavePath = $caseInsensitivePaths ? strtolower($expectedSavePath) : $expectedSavePath;
$comparablePublicPath = $caseInsensitivePaths ? strtolower($resolvedPublicPath) : $resolvedPublicPath;
$publicPrefix = rtrim($comparablePublicPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if ($comparableSavePath !== $comparableExpectedSavePath) {
    throw new ConfigurationException('The session save path must resolve to storage/sessions.');
}

if ($comparableSavePath === $comparablePublicPath || str_starts_with($comparableSavePath, $publicPrefix)) {
    throw new ConfigurationException('The session save path must remain outside public/.');
}

return [
    'name' => $name,
    'inactivity_timeout' => $inactivityTimeout,
    'absolute_lifetime' => $absoluteLifetime,
    'cookie' => [
        'lifetime' => 0,
        'secure' => $secureCookie,
        'http_only' => $httpOnly,
        'same_site' => $sameSite,
        'path' => $cookiePath,
        'domain' => $cookieDomain,
    ],
    'strict_mode' => true,
    'use_cookies' => true,
    'use_only_cookies' => true,
    'use_trans_sid' => false,
    'save_handler' => 'files',
    'save_path' => $resolvedSavePath,
    'gc_maxlifetime' => max(43200, $absoluteLifetime),
];
