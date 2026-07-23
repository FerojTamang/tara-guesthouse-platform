<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\RoomCatalogException;

final class RoomImageInput
{
    private function __construct()
    {
    }

    public static function alternativeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw RoomCatalogException::invalidInput('Alternative text is invalid.');
        }

        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);

        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > 255
            || preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1
            || strip_tags($normalized) !== $normalized
        ) {
            throw RoomCatalogException::invalidInput('Alternative text is invalid.');
        }

        return $normalized;
    }

    public static function sortOrder(mixed $value, bool $required): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            if ($required) {
                throw RoomCatalogException::invalidInput('Sort order is invalid.');
            }

            return null;
        }

        $order = RoomCatalogInput::integer($value, 'Sort order', 0);

        if ($order > 65535) {
            throw RoomCatalogException::invalidInput('Sort order is invalid.');
        }

        return $order;
    }
}
