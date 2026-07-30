<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\GalleryException;

/**
 * Validates one exact, duplicate-free gallery reorder payload.
 */
final readonly class GalleryReorderRequest
{
    /** @param list<array{id: int, sort_order: int}> $items */
    private function __construct(private array $items)
    {
    }

    public static function fromInput(mixed $items): self
    {
        if (!is_array($items) || !array_is_list($items) || $items === []) {
            throw GalleryException::invalidInput('Gallery reorder items are required.');
        }

        $normalized = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw GalleryException::invalidInput('Gallery reorder payload is invalid.');
            }

            $keys = array_keys($item);
            sort($keys);

            if ($keys !== ['id', 'sort_order']) {
                throw GalleryException::invalidInput('Gallery reorder payload is invalid.');
            }

            $id = self::positiveInteger($item['id']);
            $sortOrder = self::sortOrder($item['sort_order']);

            if (isset($seen[$id])) {
                throw GalleryException::invalidInput('Gallery reorder identifiers must be unique.');
            }

            $seen[$id] = true;
            $normalized[] = ['id' => $id, 'sort_order' => $sortOrder];
        }

        return new self($normalized);
    }

    /** @return list<array{id: int, sort_order: int}> */
    public function items(): array
    {
        return $this->items;
    }

    private static function positiveInteger(mixed $value): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $validated = filter_var($value, FILTER_VALIDATE_INT);
            $integer = is_int($validated) ? $validated : 0;
        } else {
            $integer = 0;
        }

        if ($integer <= 0) {
            throw GalleryException::invalidInput('Gallery item identifier is invalid.');
        }

        return $integer;
    }

    private static function sortOrder(mixed $value): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $validated = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => GalleryItemMetadataRequest::MAXIMUM_SORT_ORDER],
            ]);
            $integer = is_int($validated) ? $validated : -1;
        } else {
            $integer = -1;
        }

        if ($integer < 0 || $integer > GalleryItemMetadataRequest::MAXIMUM_SORT_ORDER) {
            throw GalleryException::invalidInput('Gallery sort order is invalid.');
        }

        return $integer;
    }
}
