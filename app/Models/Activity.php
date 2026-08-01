<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable Version 1 activity catalogue record.
 */
final readonly class Activity
{
    public function __construct(
        private int $id,
        private string $title,
        private string $slug,
        private string $description,
        private string $price,
        private bool $active,
        private bool $published,
        private int $sortOrder,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || !$this->isRequiredText($title, 200)
            || !$this->isSlug($slug)
            || !$this->isRequiredText($description)
            || !$this->isNonNegativeMoney($price)
            || $sortOrder < 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Activity data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
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

    /**
     * Returns the exact stored NPR decimal without floating-point conversion.
     */
    public function price(): string
    {
        return $this->price;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->active && $this->published;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function isRequiredText(string $value, ?int $maximumLength = null): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && ($maximumLength === null || mb_strlen($value, 'UTF-8') <= $maximumLength);
    }

    private function isSlug(string $value): bool
    {
        return $value !== ''
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= 200
            && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value) === 1;
    }

    private function isNonNegativeMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1;
    }
}
