<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one immutable verified-stay review. */
final readonly class Review
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    public function __construct(
        private int $id,
        private int $bookingId,
        private int $reviewInvitationId,
        private string $guestNameSnapshot,
        private int $rating,
        private ?string $title,
        private string $comment,
        private string $status,
        private ?int $moderatedBy,
        private ?DateTimeImmutable $moderatedAt,
        private ?string $moderationNote,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $bookingId <= 0
            || $reviewInvitationId <= 0
            || !$this->requiredText($guestNameSnapshot, 100, false)
            || $rating < 1
            || $rating > 5
            || !$this->optionalText($title, 150, false)
            || !$this->requiredText($comment, 65535, true)
            || !in_array($status, self::STATUSES, true)
            || ($moderatedBy !== null && $moderatedBy <= 0)
            || ($moderatedAt instanceof DateTimeImmutable && $moderatedAt->getOffset() !== 0)
            || !$this->optionalText($moderationNote, 65535, true)
            || !$this->validModerationState(
                $status,
                $moderatedBy,
                $moderatedAt,
                $moderationNote,
                $createdAt,
                $updatedAt,
            )
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
            || $updatedAt < $createdAt
        ) {
            throw new InvalidArgumentException('Review data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function bookingId(): int { return $this->bookingId; }
    public function reviewInvitationId(): int { return $this->reviewInvitationId; }
    public function guestNameSnapshot(): string { return $this->guestNameSnapshot; }
    public function rating(): int { return $this->rating; }
    public function title(): ?string { return $this->title; }
    public function comment(): string { return $this->comment; }
    public function status(): string { return $this->status; }
    public function moderatedBy(): ?int { return $this->moderatedBy; }
    public function moderatedAt(): ?DateTimeImmutable { return $this->moderatedAt; }
    public function moderationNote(): ?string { return $this->moderationNote; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function isPublic(): bool { return $this->status === self::STATUS_APPROVED; }

    private function requiredText(string $value, int $maximumLength, bool $multiline): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumLength
            && preg_match(
                $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u' : '/[\x00-\x1F\x7F]|\p{Cf}/u',
                $value,
            ) !== 1;
    }

    private function optionalText(?string $value, int $maximumLength, bool $multiline): bool
    {
        return $value === null || $this->requiredText($value, $maximumLength, $multiline);
    }

    private function validModerationState(
        string $status,
        ?int $moderatedBy,
        ?DateTimeImmutable $moderatedAt,
        ?string $moderationNote,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): bool {
        if ($status === self::STATUS_PENDING) {
            return $moderatedBy === null && $moderatedAt === null && $moderationNote === null;
        }

        return $moderatedBy !== null
            && $moderatedAt instanceof DateTimeImmutable
            && $moderatedAt >= $createdAt
            && $updatedAt >= $moderatedAt;
    }
}
