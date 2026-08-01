<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one immutable structured CMS page. */
final readonly class CmsPage
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';
    public const STATUSES = [self::DRAFT, self::PUBLISHED, self::ARCHIVED];

    public function __construct(
        private int $id,
        private string $title,
        private string $slug,
        private string $content,
        private ?string $metaTitle,
        private ?string $metaDescription,
        private string $status,
        private ?DateTimeImmutable $publishedAt,
        private int $createdBy,
        private int $updatedBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || !$this->requiredText($title, 200)
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
            || mb_strlen($slug, 'UTF-8') > 200
            || !$this->requiredText($content)
            || !$this->optionalText($metaTitle, 255)
            || !$this->optionalText($metaDescription, 500)
            || !in_array($status, self::STATUSES, true)
            || ($status === self::PUBLISHED && !$publishedAt instanceof DateTimeImmutable)
            || ($publishedAt instanceof DateTimeImmutable && $publishedAt->getOffset() !== 0)
            || $createdBy <= 0
            || $updatedBy <= 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('CMS page data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function title(): string { return $this->title; }
    public function slug(): string { return $this->slug; }
    public function content(): string { return $this->content; }
    public function metaTitle(): ?string { return $this->metaTitle; }
    public function metaDescription(): ?string { return $this->metaDescription; }
    public function status(): string { return $this->status; }
    public function publishedAt(): ?DateTimeImmutable { return $this->publishedAt; }
    public function createdBy(): int { return $this->createdBy; }
    public function updatedBy(): int { return $this->updatedBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function isPublished(): bool { return $this->status === self::PUBLISHED; }

    private function requiredText(string $value, ?int $maximumLength = null): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && ($maximumLength === null || mb_strlen($value, 'UTF-8') <= $maximumLength);
    }

    private function optionalText(?string $value, int $maximumLength): bool
    {
        return $value === null || $this->requiredText($value, $maximumLength);
    }
}
