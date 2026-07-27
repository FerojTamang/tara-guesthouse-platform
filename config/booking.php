<?php

declare(strict_types=1);

use App\Core\Environment;
use App\Exceptions\ConfigurationException;

if (!isset($environment) || !$environment instanceof Environment) {
    throw new ConfigurationException('Booking configuration requires an Environment instance.');
}

$environmentName = strtolower($environment->string('APP_ENV'));
$deadlineRaw = $environment->literalString('BOOKING_PENDING_DEADLINE_MINUTES', '');

if ($deadlineRaw === '') {
    $pendingDeadlineMinutes = 360;
} else {
    if ($deadlineRaw !== trim($deadlineRaw)) {
        throw new ConfigurationException(
            'BOOKING_PENDING_DEADLINE_MINUTES must not contain surrounding whitespace.',
        );
    }

    $pendingDeadlineMinutes = filter_var($deadlineRaw, FILTER_VALIDATE_INT);

    if (
        !is_int($pendingDeadlineMinutes)
        || $pendingDeadlineMinutes <= 0
        || $pendingDeadlineMinutes > 10080
    ) {
        throw new ConfigurationException(
            'BOOKING_PENDING_DEADLINE_MINUTES must be between 1 and 10080.',
        );
    }
}

$key = $environment->literalString('BOOKING_ACCESS_TOKEN_KEY', '');

if ($key === '' && $environmentName === 'testing') {
    $key = hash('sha256', 'tara-guest-house-testing-booking-access-token-key');
}

if ($key === '') {
    $accessTokenKey = null;
} else {
    if (
        $key !== trim($key)
        || strlen($key) < 32
        || strlen($key) > 128
        || preg_match('/[\x00-\x20\x7F]/', $key) === 1
    ) {
        throw new ConfigurationException(
            'BOOKING_ACCESS_TOKEN_KEY must contain 32 to 128 non-whitespace bytes.',
        );
    }

    $accessTokenKey = $key;
}

return [
    'pending_deadline_minutes' => $pendingDeadlineMinutes,
    'access_token_key' => $accessTokenKey,
];
