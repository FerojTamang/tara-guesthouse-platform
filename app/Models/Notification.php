<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one retained Administrator-owned in-application notification. */
final readonly class Notification
{
    public const TYPE_MAXIMUM_LENGTH = 50;
    public const TITLE_MAXIMUM_LENGTH = 150;
    public const MESSAGE_MAXIMUM_LENGTH = 65535;
    public const RELATED_TYPE_MAXIMUM_LENGTH = 50;

    public function __construct(
        private int $id,
        private int $userId,
        private string $type,
        private string $title,
        private string $message,
        private ?string $relatedType,
        private ?int $relatedId,
        private ?DateTimeImmutable $readAt,
        private DateTimeImmutable $createdAt,
    ) {
        if (
            $id <= 0
            || $userId <= 0
            || !$this->validType($type, self::TYPE_MAXIMUM_LENGTH)
            || !$this->requiredSingleLine($title, self::TITLE_MAXIMUM_LENGTH)
            || !$this->requiredMessage($message)
            || ($relatedType !== null && !$this->validType($relatedType, self::RELATED_TYPE_MAXIMUM_LENGTH))
            || ($relatedId !== null && $relatedId <= 0)
            || ($readAt instanceof DateTimeImmutable && $readAt->getOffset() !== 0)
            || $createdAt->getOffset() !== 0
            || ($readAt instanceof DateTimeImmutable && $readAt < $createdAt)
        ) {
            throw new InvalidArgumentException('Notification data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function userId(): int { return $this->userId; }
    public function type(): string { return $this->type; }
    public function title(): string { return $this->title; }
    public function message(): string { return $this->message; }
    public function relatedType(): ?string { return $this->relatedType; }
    public function relatedId(): ?int { return $this->relatedId; }
    public function readAt(): ?DateTimeImmutable { return $this->readAt; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function isRead(): bool { return $this->readAt instanceof DateTimeImmutable; }

    private function validType(string $value, int $maximumLength): bool
    {
        return $value !== ''
            && $value === trim($value)
            && strlen($value) <= $maximumLength
            && preg_match('/^[a-z][a-z0-9_]*$/D', $value) === 1;
    }

    private function requiredSingleLine(string $value, int $maximumLength): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumLength
            && preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $value) !== 1;
    }

    private function requiredMessage(string $value): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= self::MESSAGE_MAXIMUM_LENGTH
            && strlen($value) <= self::MESSAGE_MAXIMUM_LENGTH
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $value) !== 1;
    }
}
