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
    private string $summary;
    private ?string $featuredImagePath;
    private ?string $priceUnit;
    private ?string $durationLabel;
    private ?string $location;
    private bool $homepageFeatured;
    private int $homepageSortOrder;

    public function __construct(
        private int $id,
        private string $title,
        private string $slug,
        private string $description,
        private ?string $price,
        private bool $active,
        private bool $published,
        private int $sortOrder,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        ?string $summary = null,
        ?string $featuredImagePath = null,
        ?string $priceUnit = null,
        ?string $durationLabel = null,
        ?string $location = null,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ) {
        $summary ??= mb_substr($description, 0, 320, 'UTF-8');

        if (
            $id <= 0
            || !$this->isRequiredText($title, 200)
            || !$this->isSlug($slug)
            || !$this->isRequiredText($description)
            || !$this->isOptionalMoney($price)
            || !$this->isRequiredText($summary, 320)
            || !$this->isOptionalPath($featuredImagePath)
            || !$this->isOptionalSingleLine($priceUnit, 40)
            || !$this->isOptionalSingleLine($durationLabel, 80)
            || !$this->isOptionalSingleLine($location, 120)
            || $sortOrder < 0
            || $homepageSortOrder < 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Activity data is invalid.');
        }

        $this->summary = $summary;
        $this->featuredImagePath = $featuredImagePath;
        $this->priceUnit = $priceUnit;
        $this->durationLabel = $durationLabel;
        $this->location = $location;
        $this->homepageFeatured = $homepageFeatured;
        $this->homepageSortOrder = $homepageSortOrder;
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

    public function summary(): string
    {
        return $this->summary;
    }

    public function featuredImagePath(): ?string
    {
        return $this->featuredImagePath;
    }

    /**
     * Returns the exact stored NPR decimal without floating-point conversion.
     */
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

    public function isHomepageFeatured(): bool
    {
        return $this->homepageFeatured;
    }

    public function homepageSortOrder(): int
    {
        return $this->homepageSortOrder;
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

    private function isOptionalMoney(?string $value): bool
    {
        return $value === null
            || preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1;
    }

    private function isOptionalSingleLine(?string $value, int $maximumLength): bool
    {
        return $value === null
            || ($this->isRequiredText($value, $maximumLength) && !str_contains($value, "\n"));
    }

    private function isOptionalPath(?string $value): bool
    {
        return $value === null
            || (mb_strlen($value, 'UTF-8') <= 500
                && preg_match('/^gallery\/[a-f0-9]{64}\.(?:jpg|png|webp)$/D', $value) === 1);
    }
}
