<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\RoomCatalogException;
use App\Models\Room;

/**
 * Validates and normalizes scalar room-catalogue form values.
 */
final class RoomCatalogInput
{
    private const MAX_INT = '2147483647';

    private function __construct()
    {
    }

    public static function requiredText(mixed $value, string $label, int $maxCharacters): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        $normalized = self::trimUnicode($value, $label);

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > $maxCharacters
            || preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1
        ) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }

    public static function optionalDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw RoomCatalogException::invalidInput('Description is invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $normalized = self::trimUnicode($normalized, 'Description');

        if ($normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > 5000
            || preg_match('/(?:[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf})/u', $normalized) === 1
        ) {
            throw RoomCatalogException::invalidInput('Description is invalid.');
        }

        return $normalized;
    }

    public static function slug(mixed $value): string
    {
        if (!is_string($value)) {
            throw RoomCatalogException::invalidInput('Slug is invalid.');
        }

        $normalized = trim($value);

        if (
            $normalized === ''
            || strlen($normalized) > 120
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $normalized) !== 1
        ) {
            throw RoomCatalogException::invalidInput('Slug is invalid.');
        }

        return $normalized;
    }

    public static function money(mixed $value, string $label = 'Base price'): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        $normalized = trim($value);

        if (preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D', $normalized) !== 1) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $money = $whole . '.' . str_pad($fraction, 2, '0');

        if ($money === '0.00') {
            throw RoomCatalogException::invalidInput($label . ' must be greater than zero.');
        }

        return $money;
    }

    public static function integer(mixed $value, string $label, int $minimum): int
    {
        if (is_int($value)) {
            $normalized = (string) $value;
        } elseif (is_string($value)) {
            $normalized = trim($value);
        } else {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        if (preg_match('/^[0-9]+$/D', $normalized) !== 1) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        $normalized = ltrim($normalized, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        if (
            strlen($normalized) > strlen(self::MAX_INT)
            || (strlen($normalized) === strlen(self::MAX_INT) && strcmp($normalized, self::MAX_INT) > 0)
        ) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        $integer = (int) $normalized;

        if ($integer < $minimum) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        return $integer;
    }

    public static function explicitBoolean(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized === '0' || $normalized === '1') {
                return $normalized === '1';
            }
        }

        throw RoomCatalogException::invalidInput($label . ' is invalid.');
    }

    public static function identifier(mixed $value, string $label): int
    {
        if (is_int($value)) {
            $normalized = (string) $value;
        } elseif (is_string($value)) {
            $normalized = trim($value);
        } else {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        if (preg_match('/^[0-9]+$/D', $normalized) !== 1) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        $normalized = ltrim($normalized, '0');

        if ($normalized === '') {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        $maximum = (string) PHP_INT_MAX;

        if (
            strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        return (int) $normalized;
    }

    /** @return list<int> */
    public static function identifierList(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw RoomCatalogException::invalidInput($label . ' selections are invalid.');
        }

        $identifiers = [];

        foreach ($value as $item) {
            $identifier = self::identifier($item, $label);

            if (isset($identifiers[$identifier])) {
                throw RoomCatalogException::invalidInput($label . ' selections are invalid.');
            }

            $identifiers[$identifier] = $identifier;
        }

        ksort($identifiers, SORT_NUMERIC);

        return array_values($identifiers);
    }

    public static function operationalStatus(mixed $value): string
    {
        if (!is_string($value)) {
            throw RoomCatalogException::invalidInput('Operational status is invalid.');
        }

        $normalized = trim($value);

        if (!in_array($normalized, [
            Room::STATUS_ACTIVE,
            Room::STATUS_MAINTENANCE,
            Room::STATUS_INACTIVE,
        ], true)) {
            throw RoomCatalogException::invalidInput('Operational status is invalid.');
        }

        return $normalized;
    }

    public static function optionalIcon(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw RoomCatalogException::invalidInput('Icon identifier is invalid.');
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (
            strlen($normalized) > 100
            || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $normalized) !== 1
        ) {
            throw RoomCatalogException::invalidInput('Icon identifier is invalid.');
        }

        return $normalized;
    }

    private static function trimUnicode(string $value, string $label): string
    {
        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);

        if (!is_string($normalized)) {
            throw RoomCatalogException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }
}
