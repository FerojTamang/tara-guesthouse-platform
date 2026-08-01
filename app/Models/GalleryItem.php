<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable Version 1 gallery item.
 */
final readonly class GalleryItem
{
    private bool $homepageFeatured;
    private int $homepageSortOrder;

    public function __construct(
        private int $id,
        private ?int $categoryId,
        private string $title,
        private ?string $caption,
        private string $filePath,
        private string $altText,
        private int $sortOrder,
        private bool $active,
        private bool $published,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ) {
        if (
            $id <= 0
            || ($categoryId !== null && $categoryId <= 0)
            || !$this->isRequiredText($title, 200)
            || !$this->isOptionalText($caption)
            || !$this->isSafeRelativePath($filePath)
            || !$this->isRequiredText($altText, 500)
            || $sortOrder < 0
            || $homepageSortOrder < 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Gallery item data is invalid.');
        }

        $this->homepageFeatured = $homepageFeatured;
        $this->homepageSortOrder = $homepageSortOrder;
    }

    public function id(): int
    {
        return $this->id;
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

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function altText(): string
    {
        return $this->altText;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
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

    private function isRequiredText(string $value, int $maximumLength): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumLength;
    }

    private function isOptionalText(?string $value): bool
    {
        return $value === null
            || ($value !== '' && $value === trim($value) && mb_check_encoding($value, 'UTF-8'));
    }

    private function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && mb_check_encoding($path, 'UTF-8')
            && mb_strlen($path, 'UTF-8') <= 500
            && preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]*(?:\/[A-Za-z0-9][A-Za-z0-9._-]*)*$/D',
                $path,
            ) === 1;
    }
}
