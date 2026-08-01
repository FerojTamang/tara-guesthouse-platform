<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('Mail configuration requires an Environment instance.');
}

$environmentName = strtolower($environment->string('APP_ENV'));
$protectedEnvironment = in_array($environmentName, ['staging', 'production'], true);

$plain = static function (string $key, string $default = '') use ($environment): string {
    $value = $environment->literalString($key, $default);

    if ($value !== trim($value) || preg_match('/[\r\n\x00]/', $value) === 1) {
        throw new ConfigurationException(sprintf('%s is invalid.', $key));
    }

    return $value;
};

$positiveInteger = static function (
    string $key,
    int $default,
    int $maximum,
) use ($environment): int {
    $raw = $environment->literalString($key, '');

    if ($raw === '') {
        return $default;
    }

    if ($raw !== trim($raw) || preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
        throw new ConfigurationException(sprintf('%s must be a positive integer.', $key));
    }

    $value = filter_var($raw, FILTER_VALIDATE_INT);

    if (!is_int($value) || $value < 1 || $value > $maximum) {
        throw new ConfigurationException(sprintf('%s is outside the supported range.', $key));
    }

    return $value;
};

$validAddress = static function (string $key, string $value, bool $required): ?string {
    if ($value === '') {
        if ($required) {
            throw new ConfigurationException(sprintf('%s is required when SMTP is enabled.', $key));
        }

        return null;
    }

    if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        throw new ConfigurationException(sprintf('%s must be a valid email address.', $key));
    }

    return $value;
};

$validName = static function (string $key, string $value, bool $required): ?string {
    if ($value === '') {
        if ($required) {
            throw new ConfigurationException(sprintf('%s is required when SMTP is enabled.', $key));
        }

        return null;
    }

    if (
        !mb_check_encoding($value, 'UTF-8')
        || mb_strlen($value, 'UTF-8') > 150
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1
    ) {
        throw new ConfigurationException(sprintf('%s is invalid.', $key));
    }

    return $value;
};

$host = $plain('MAIL_HOST');
$port = $positiveInteger('MAIL_PORT', 587, 65535);
$encryption = strtolower($plain('MAIL_ENCRYPTION', 'tls'));
$username = $plain('MAIL_USERNAME');
$password = $environment->literalString('MAIL_PASSWORD', '');
$fromAddressRaw = $plain('MAIL_FROM_ADDRESS');
$fromNameRaw = $plain('MAIL_FROM_NAME', 'Tara Guest House');
$replyToAddressRaw = $plain('MAIL_REPLY_TO_ADDRESS');
$replyToNameRaw = $plain('MAIL_REPLY_TO_NAME');
$timeout = $positiveInteger('MAIL_TIMEOUT', 10, 60);

if (preg_match('/[\r\n\x00]/', $password) === 1 || strlen($password) > 512) {
    throw new ConfigurationException('MAIL_PASSWORD is invalid.');
}

if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
    throw new ConfigurationException('MAIL_ENCRYPTION must be tls, ssl, or none.');
}

$enabled = $host !== '';

if (!$enabled) {
    if (
        $protectedEnvironment
        || $username !== ''
        || $password !== ''
        || $fromAddressRaw !== ''
        || $replyToAddressRaw !== ''
        || $replyToNameRaw !== ''
    ) {
        throw new ConfigurationException('SMTP configuration is incomplete.');
    }

    return [
        'enabled' => false,
        'host' => null,
        'port' => $port,
        'encryption' => $encryption,
        'username' => null,
        'password' => null,
        'from_address' => null,
        'from_name' => $validName('MAIL_FROM_NAME', $fromNameRaw, true),
        'reply_to_address' => null,
        'reply_to_name' => null,
        'timeout_seconds' => $timeout,
    ];
}

$hostValid = filter_var($host, FILTER_VALIDATE_IP) !== false
    || preg_match(
        '/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/D',
        $host,
    ) === 1;

if (!$hostValid) {
    throw new ConfigurationException('MAIL_HOST must be a plain hostname or IP address.');
}

if (($username === '') !== ($password === '')) {
    throw new ConfigurationException('MAIL_USERNAME and MAIL_PASSWORD must be configured together.');
}

if ($protectedEnvironment && $encryption === 'none') {
    throw new ConfigurationException('Staging and production SMTP require TLS or SSL encryption.');
}

if ($username !== '' && $encryption === 'none') {
    throw new ConfigurationException('SMTP authentication requires TLS or SSL encryption.');
}

if ($username !== '' && strlen($username) > 512) {
    throw new ConfigurationException('MAIL_USERNAME is invalid.');
}

$fromAddress = $validAddress('MAIL_FROM_ADDRESS', $fromAddressRaw, true);
$fromName = $validName('MAIL_FROM_NAME', $fromNameRaw, true);
$replyToAddress = $validAddress('MAIL_REPLY_TO_ADDRESS', $replyToAddressRaw, false);
$replyToName = $validName('MAIL_REPLY_TO_NAME', $replyToNameRaw, false);

if ($replyToAddress === null && $replyToName !== null) {
    throw new ConfigurationException('MAIL_REPLY_TO_NAME requires MAIL_REPLY_TO_ADDRESS.');
}

return [
    'enabled' => true,
    'host' => $host,
    'port' => $port,
    'encryption' => $encryption,
    'username' => $username === '' ? null : $username,
    'password' => $password === '' ? null : $password,
    'from_address' => $fromAddress,
    'from_name' => $fromName,
    'reply_to_address' => $replyToAddress,
    'reply_to_name' => $replyToName,
    'timeout_seconds' => $timeout,
];
