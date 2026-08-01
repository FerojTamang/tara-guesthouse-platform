<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\GalleryException;

/**
 * Validates approved Version 1 gallery-item metadata without accepting a file path.
 */
final readonly class GalleryItemMetadataRequest
{
    public const MAXIMUM_CAPTION_BYTES = 65535;
    public const MAXIMUM_SORT_ORDER = 4294967295;

    private function __construct(
        private ?int $categoryId,
        private string $title,
        private ?string $caption,
        private string $alternativeText,
        private int $sortOrder,
        private bool $homepageFeatured,
        private int $homepageSortOrder,
    ) {
    }

    public static function fromInput(
        mixed $categoryId,
        mixed $title,
        mixed $caption,
        mixed $alternativeText,
        mixed $sortOrder,
        mixed $homepageFeatured = false,
        mixed $homepageSortOrder = 0,
    ): self {
        return new self(
            self::normalizeCategoryId($categoryId),
            self::requiredSingleLine($title, 'Gallery item title', 200),
            self::normalizeCaption($caption),
            self::requiredText($alternativeText, 'Gallery alternative text', 500),
            self::normalizeSortOrder($sortOrder),
            self::normalizeBoolean($homepageFeatured),
            self::normalizeHomepageSortOrder($homepageSortOrder),
        );
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function caption(): ?string
    {
        return $this->caption;
    }

    public function alternativeText(): string
    {
        return $this->alternativeText;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isHomepageFeatured(): bool
    {
        return $this->homepageFeatured;
    }

    public function homepageSortOrder(): int
    {
        return $this->homepageSortOrder;
    }

    private static function normalizeCategoryId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            $identifier = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $validated = filter_var($value, FILTER_VALIDATE_INT);
            $identifier = is_int($validated) ? $validated : 0;
        } else {
            $identifier = 0;
        }

        if ($identifier <= 0) {
            throw GalleryException::invalidInput('Gallery category identifier is invalid.');
        }

        return $identifier;
    }

    private static function requiredSingleLine(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): string {
        $normalized = self::requiredText($value, $label, $maximumCharacters);

        if (str_contains($normalized, "\n")) {
            throw GalleryException::invalidInput($label . ' must use one line.');
        }

        return $normalized;
    }

    private static function requiredText(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): string {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GalleryException::invalidInput($label . ' is required.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > $maximumCharacters
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw GalleryException::invalidInput(
                sprintf('%s is required and must not exceed %d characters.', $label, $maximumCharacters),
            );
        }

        return $normalized;
    }

    private static function normalizeCaption(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GalleryException::invalidInput('Gallery caption is invalid.');
        }

        $caption = str_replace(["\r\n", "\r"], "\n", trim($value));

        if ($caption === '') {
            return null;
        }

        if (
            strlen($caption) > self::MAXIMUM_CAPTION_BYTES
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $caption) === 1
        ) {
            throw GalleryException::invalidInput('Gallery caption must fit the approved text field.');
        }

        return $caption;
    }

    private static function normalizeSortOrder(mixed $value): int
    {
        if (is_int($value)) {
            $sortOrder = $value;
        } elseif (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $validated = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 0,
                    'max_range' => self::MAXIMUM_SORT_ORDER,
                ],
            ]);
            $sortOrder = is_int($validated) ? $validated : -1;
        } else {
            $sortOrder = -1;
        }

        if ($sortOrder < 0 || $sortOrder > self::MAXIMUM_SORT_ORDER) {
            throw GalleryException::invalidInput('Gallery sort order is invalid.');
        }

        return $sortOrder;
    }

    private static function normalizeHomepageSortOrder(mixed $value): int
    {
        try {
            return self::normalizeSortOrder($value);
        } catch (GalleryException) {
            throw GalleryException::invalidInput('Gallery homepage sort order is invalid.');
        }
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === '1') {
            return true;
        }

        if ($value === '0') {
            return false;
        }

        throw GalleryException::invalidInput('Gallery homepage selection must be true or false.');
    }
}
