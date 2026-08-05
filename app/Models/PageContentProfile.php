<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Stores queryable professional presentation metadata for one CMS page. */
final readonly class PageContentProfile
{
    public const TYPES = ['page', 'news', 'notice', 'policy'];
    public const NOTICE_STYLES = ['information', 'important', 'warning', 'event'];

    public function __construct(
        private int $pageId,
        private string $contentType,
        private ?string $summary,
        private ?string $noticeStyle,
        private ?DateTimeImmutable $publishAt,
        private ?DateTimeImmutable $expiresAt,
        private bool $featured,
        private bool $pinned,
        private ?int $featuredMediaId,
        private ?string $socialTitle,
        private ?string $socialDescription,
        private ?int $socialMediaId,
        private bool $seoNoindex,
        private int $updatedBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $pageId <= 0
            || !in_array($contentType, self::TYPES, true)
            || !$this->optionalText($summary, 500)
            || ($noticeStyle !== null && !in_array($noticeStyle, self::NOTICE_STYLES, true))
            || ($contentType !== 'notice' && $noticeStyle !== null)
            || ($publishAt instanceof DateTimeImmutable && $publishAt->getOffset() !== 0)
            || ($expiresAt instanceof DateTimeImmutable && $expiresAt->getOffset() !== 0)
            || ($expiresAt instanceof DateTimeImmutable && !$publishAt instanceof DateTimeImmutable)
            || ($publishAt instanceof DateTimeImmutable && $expiresAt instanceof DateTimeImmutable && $expiresAt <= $publishAt)
            || ($featuredMediaId !== null && $featuredMediaId <= 0)
            || !$this->optionalText($socialTitle, 255)
            || !$this->optionalText($socialDescription, 500)
            || ($socialMediaId !== null && $socialMediaId <= 0)
            || $updatedBy <= 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Page content profile data is invalid.');
        }
    }

    public function pageId(): int { return $this->pageId; }
    public function contentType(): string { return $this->contentType; }
    public function summary(): ?string { return $this->summary; }
    public function noticeStyle(): ?string { return $this->noticeStyle; }
    public function publishAt(): ?DateTimeImmutable { return $this->publishAt; }
    public function expiresAt(): ?DateTimeImmutable { return $this->expiresAt; }
    public function isFeatured(): bool { return $this->featured; }
    public function isPinned(): bool { return $this->pinned; }
    public function featuredMediaId(): ?int { return $this->featuredMediaId; }
    public function socialTitle(): ?string { return $this->socialTitle; }
    public function socialDescription(): ?string { return $this->socialDescription; }
    public function socialMediaId(): ?int { return $this->socialMediaId; }
    public function seoNoindex(): bool { return $this->seoNoindex; }
    public function updatedBy(): int { return $this->updatedBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        if ($now->getOffset() !== 0) {
            throw new InvalidArgumentException('Expiry comparisons require UTC time.');
        }
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt <= $now;
    }

    private function optionalText(?string $value, int $maximumLength): bool
    {
        return $value === null || (
            $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && !str_contains($value, "\0")
            && mb_strlen($value, 'UTF-8') <= $maximumLength
        );
    }
}
