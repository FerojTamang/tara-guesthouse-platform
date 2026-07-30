<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\GalleryException;

/**
 * Validates one exact Administrator gallery-category payload.
 */
final readonly class GalleryCategoryRequest
{
    private function __construct(
        private string $name,
        private string $slug,
        private bool $active,
    ) {
    }

    public static function fromInput(mixed $name, mixed $slug, mixed $active): self
    {
        if (!is_bool($active)) {
            throw GalleryException::invalidInput('Gallery category active state must be true or false.');
        }

        return new self(
            self::requiredSingleLine($name, 'Gallery category name', 150),
            self::normalizeSlug($slug),
            $active,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    private static function requiredSingleLine(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): string {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GalleryException::invalidInput($label . ' is required.');
        }

        $normalized = trim($value);

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > $maximumCharacters
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw GalleryException::invalidInput(
                sprintf('%s is required and must not exceed %d characters.', $label, $maximumCharacters),
            );
        }

        return $normalized;
    }

    private static function normalizeSlug(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GalleryException::invalidInput('Gallery category slug is invalid.');
        }

        $slug = trim($value);

        if (
            $slug === ''
            || mb_strlen($slug, 'UTF-8') > 200
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
        ) {
            throw GalleryException::invalidInput(
                'Gallery category slug must use lowercase letters, numbers, and single hyphens only.',
            );
        }

        return $slug;
    }
}
