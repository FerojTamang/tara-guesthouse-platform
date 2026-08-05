<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('HTTP configuration requires an Environment instance.');
}

$environmentName = strtolower($environment->string('APP_ENV'));

if (!in_array($environmentName, ['local', 'testing', 'staging', 'production'], true)) {
    throw new ConfigurationException('HTTP configuration requires an approved application environment.');
}

$protectedEnvironment = in_array($environmentName, ['staging', 'production'], true);
$imageSources = $protectedEnvironment
    ? "'self' data: https:"
    : "'self' data: http: https:";

$contentSecurityPolicy = implode('; ', [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'none'",
    "form-action 'self'",
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
    sprintf('img-src %s', $imageSources),
    "font-src 'self' data: https://cdn.jsdelivr.net",
    "connect-src 'self'",
    "media-src 'self' https:",
    "frame-src 'none'",
    "worker-src 'self'",
    "manifest-src 'self'",
]);

if ($protectedEnvironment) {
    $contentSecurityPolicy .= '; upgrade-insecure-requests';
}

return [
    'security_headers' => [
        'content_security_policy' => $contentSecurityPolicy,
        'permissions_policy' => implode(', ', [
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'camera=()',
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'screen-wake-lock=()',
            'serial=()',
            'usb=()',
            'web-share=(self)',
            'xr-spatial-tracking=()',
        ]),
        'strict_transport_security' => $protectedEnvironment
            ? 'max-age=31536000'
            : null,
    ],
];
