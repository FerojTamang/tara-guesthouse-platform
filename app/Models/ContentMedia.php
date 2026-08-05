<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one reusable, validated public content image. */
final readonly class ContentMedia
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private int $id,
        private string $storagePath,
        private string $originalName,
        private string $mimeType,
        private string $extension,
        private int $byteSize,
        private int $width,
        private int $height,
        private string $sha256,
        private ?string $title,
        private string $altText,
        private ?string $caption,
        private int $uploadedBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
        private int $usageCount,
    ) {
        if (
            $id <= 0
            || !isset(self::MIME_EXTENSIONS[$mimeType])
            || self::MIME_EXTENSIONS[$mimeType] !== $extension
            || preg_match('/^uploads\/content\/[a-f0-9]{64}\.(?:jpg|png|webp)$/D', $storagePath) !== 1
            || !$this->requiredText($originalName, 255)
            || $byteSize <= 0
            || $width <= 0
            || $height <= 0
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
            || !$this->optionalText($title, 200)
            || !$this->requiredText($altText, 255)
            || !$this->optionalText($caption, 500)
            || $uploadedBy <= 0
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
            || ($deletedAt instanceof DateTimeImmutable && $deletedAt->getOffset() !== 0)
            || $usageCount < 0
        ) {
            throw new InvalidArgumentException('Content media data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function storagePath(): string { return $this->storagePath; }
    public function publicUrl(): string { return '/' . $this->storagePath; }
    public function originalName(): string { return $this->originalName; }
    public function mimeType(): string { return $this->mimeType; }
    public function extension(): string { return $this->extension; }
    public function byteSize(): int { return $this->byteSize; }
    public function width(): int { return $this->width; }
    public function height(): int { return $this->height; }
    public function sha256(): string { return $this->sha256; }
    public function title(): ?string { return $this->title; }
    public function altText(): string { return $this->altText; }
    public function caption(): ?string { return $this->caption; }
    public function uploadedBy(): int { return $this->uploadedBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function deletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
    public function usageCount(): int { return $this->usageCount; }
    public function isDeleted(): bool { return $this->deletedAt instanceof DateTimeImmutable; }
    public function isInUse(): bool { return $this->usageCount > 0; }

    private function requiredText(string $value, int $maximumLength): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && !str_contains($value, "\0")
            && mb_strlen($value, 'UTF-8') <= $maximumLength;
    }

    private function optionalText(?string $value, int $maximumLength): bool
    {
        return $value === null || $this->requiredText($value, $maximumLength);
    }
}
