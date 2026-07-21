<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Escapes scalar presentation values for HTML text and quoted attributes.
 *
 * This is not a JavaScript, CSS, JSON, URL, or URL-scheme sanitizer.
 */
final class Escape
{
    public static function html(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Only scalar HTML presentation values may be escaped.');
        }

        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8',
        );
    }
}
