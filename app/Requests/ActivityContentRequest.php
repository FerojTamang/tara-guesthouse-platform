<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ActivityException;

/**
 * Validates and normalizes the approved Version 1 activity content fields.
 */
final readonly class ActivityContentRequest
{
    public const MAXIMUM_DESCRIPTION_BYTES = 65535;
    public const MAXIMUM_SORT_ORDER = 4294967295;

    private function __construct(
        private string $title,
        private string $slug,
        private string $description,
        private string $price,
        private int $sortOrder,
    ) {
    }

    public static function fromInput(
        mixed $title,
        mixed $slug,
        mixed $description,
        mixed $price,
        mixed $sortOrder,
    ): self {
        $normalizedTitle = self::requiredSingleLine($title, 'Activity title', 200);
        $normalizedSlug = self::normalizeSlug($slug);
        $normalizedDescription = self::normalizeDescription($description);
        $normalizedPrice = self::normalizePrice($price);
        $normalizedSortOrder = self::normalizeSortOrder($sortOrder);

        return new self(
            $normalizedTitle,
            $normalizedSlug,
            $normalizedDescription,
            $normalizedPrice,
            $normalizedSortOrder,
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function price(): string
    {
        return $this->price;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    private static function requiredSingleLine(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): string {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ActivityException::invalidInput($label . ' is required.');
        }

        $normalized = trim($value);

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > $maximumCharacters
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ActivityException::invalidInput(
                sprintf('%s is required and must not exceed %d characters.', $label, $maximumCharacters),
            );
        }

        return $normalized;
    }

    private static function normalizeSlug(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ActivityException::invalidInput('Activity slug is invalid.');
        }

        $slug = trim($value);

        if (
            $slug === ''
            || mb_strlen($slug, 'UTF-8') > 200
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
        ) {
            throw ActivityException::invalidInput(
                'Activity slug must use lowercase letters, numbers, and single hyphens only.',
            );
        }

        return $slug;
    }

    private static function normalizeDescription(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ActivityException::invalidInput('Activity description is required.');
        }

        $description = str_replace(["\r\n", "\r"], "\n", trim($value));

        if (
            $description === ''
            || strlen($description) > self::MAXIMUM_DESCRIPTION_BYTES
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $description) === 1
        ) {
            throw ActivityException::invalidInput(
                'Activity description is required and must fit the approved text field.',
            );
        }

        return $description;
    }

    private static function normalizePrice(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw ActivityException::invalidInput(
                'Activity price must be a non-negative NPR amount with exactly two decimal places.',
            );
        }

        $price = trim((string) $value);

        if (preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $price) !== 1) {
            throw ActivityException::invalidInput(
                'Activity price must be a non-negative NPR amount with exactly two decimal places.',
            );
        }

        return $price;
    }

    private static function normalizeSortOrder(mixed $value): int
    {
        if (is_int($value)) {
            $sortOrder = $value;
        } elseif (
            is_string($value)
            && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) === 1
        ) {
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
            throw ActivityException::invalidInput('Activity sort order is invalid.');
        }

        return $sortOrder;
    }
}
