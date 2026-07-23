<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable stored room-image metadata record.
 */
final readonly class RoomImage
{
    /**
     * Creates passive room-image metadata from validated persistence data.
     */
    public function __construct(
        private int $id,
        private int $roomId,
        private string $filePath,
        private ?string $altText,
        private int $sortOrder,
        private bool $primary,
        private DateTimeImmutable $createdAt,
    ) {
        if (
            $id <= 0
            || $roomId <= 0
            || !$this->isSafeRelativePath($filePath)
            || $sortOrder < 0
            || $createdAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Room image data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function roomId(): int
    {
        return $this->roomId;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function altText(): ?string
    {
        return $this->altText;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_contains($path, '\\')
            || str_contains($path, '//')
            || str_contains($path, "\0")
        ) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
