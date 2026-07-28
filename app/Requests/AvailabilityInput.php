<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AvailabilityException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Validates and normalizes scalar availability input without reading HTTP state.
 */
final class AvailabilityInput
{
    private const MAX_DATABASE_INTEGER = '2147483647';

    private function __construct()
    {
    }

    public static function date(mixed $value, string $label): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        $normalized = trim($value);

        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $normalized) !== 1) {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $normalized,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || $date->format('Y-m-d') !== $normalized
            || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
        ) {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        return $date;
    }

    public static function positiveInteger(mixed $value, string $label): int
    {
        return self::integer($value, $label, 1);
    }

    public static function nonNegativeInteger(mixed $value, string $label): int
    {
        return self::integer($value, $label, 0);
    }

    private static function integer(mixed $value, string $label, int $minimum): int
    {
        if (is_int($value)) {
            $normalized = (string) $value;
        } elseif (is_string($value)) {
            $normalized = trim($value);
        } else {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        if (preg_match('/^[0-9]+$/D', $normalized) !== 1) {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        $normalized = ltrim($normalized, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        if (
            strlen($normalized) > strlen(self::MAX_DATABASE_INTEGER)
            || (
                strlen($normalized) === strlen(self::MAX_DATABASE_INTEGER)
                && strcmp($normalized, self::MAX_DATABASE_INTEGER) > 0
            )
        ) {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        $integer = (int) $normalized;

        if ($integer < $minimum) {
            throw AvailabilityException::invalidInput($label . ' is invalid.');
        }

        return $integer;
    }
}
