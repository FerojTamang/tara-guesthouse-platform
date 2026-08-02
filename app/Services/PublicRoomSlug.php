<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Defines the canonical public room slug shared by catalogue and availability reads.
 */
final class PublicRoomSlug
{
    public static function isCanonical(string $slug): bool
    {
        return preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) === 1;
    }
}
