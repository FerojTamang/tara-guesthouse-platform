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
        private string $summary,
        private string $description,
        private ?string $price,
        private ?string $priceUnit,
        private ?string $durationLabel,
        private ?string $location,
        private int $sortOrder,
        private bool $homepageFeatured,
        private int $homepageSortOrder,
    ) {
    }

    public static function fromInput(
        mixed $title,
        mixed $slug,
        mixed $summary,
        mixed $description,
        mixed $price,
        mixed $priceUnit,
        mixed $durationLabel,
        mixed $location,
        mixed $sortOrder,
        mixed $homepageFeatured,
        mixed $homepageSortOrder,
    ): self {
        $normalizedTitle = self::requiredSingleLine($title, 'Activity title', 200);
        $normalizedSlug = self::normalizeSlug($slug);
        $normalizedSummary = self::requiredSingleLine($summary, 'Activity summary', 320);
        $normalizedDescription = self::normalizeDescription($description);
        $normalizedPrice = self::normalizePrice($price);
        $normalizedPriceUnit = self::optionalSingleLine($priceUnit, 'Activity price unit', 40);

        if ($normalizedPrice !== null && $normalizedPriceUnit === null) {
            throw ActivityException::invalidInput('Activity price unit is required when a price is provided.');
        }

        if ($normalizedPrice === null && $normalizedPriceUnit !== null) {
            throw ActivityException::invalidInput('Activity price unit requires a price.');
        }
        $normalizedSortOrder = self::normalizeSortOrder($sortOrder);

        return new self(
            $normalizedTitle,
            $normalizedSlug,
            $normalizedSummary,
            $normalizedDescription,
            $normalizedPrice,
            $normalizedPriceUnit,
            self::optionalSingleLine($durationLabel, 'Activity duration', 80),
            self::optionalSingleLine($location, 'Activity location', 120),
            $normalizedSortOrder,
            self::normalizeBoolean($homepageFeatured, 'Activity homepage selection'),
            self::normalizeSortOrder($homepageSortOrder, 'Activity homepage sort order'),
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

    public function summary(): string
    {
        return $this->summary;
    }

    public function price(): ?string
    {
        return $this->price;
    }

    public function priceUnit(): ?string
    {
        return $this->priceUnit;
    }

    public function durationLabel(): ?string
    {
        return $this->durationLabel;
    }

    public function location(): ?string
    {
        return $this->location;
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

    private static function normalizePrice(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

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

    private static function normalizeSortOrder(mixed $value, string $label = 'Activity sort order'): int
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
            throw ActivityException::invalidInput($label . ' is invalid.');
        }

        return $sortOrder;
    }

    private static function optionalSingleLine(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ActivityException::invalidInput($label . ' is invalid.');
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > $maximumCharacters
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ActivityException::invalidInput(
                sprintf('%s must not exceed %d characters.', $label, $maximumCharacters),
            );
        }

        return $normalized;
    }

    private static function normalizeBoolean(mixed $value, string $label): bool
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

        throw ActivityException::invalidInput($label . ' must be true or false.');
    }
}
