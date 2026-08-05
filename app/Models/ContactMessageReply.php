<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one retained Administrator reply delivery attempt. */
final readonly class ContactMessageReply
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
    ];

    public function __construct(
        private int $id,
        private int $contactMessageId,
        private string $replyBody,
        private int $sentBy,
        private string $deliveryStatus,
        private ?DateTimeImmutable $sentAt,
        private ?string $failureCode,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $contactMessageId <= 0
            || !$this->validBody($replyBody)
            || $sentBy <= 0
            || !in_array($deliveryStatus, self::STATUSES, true)
            || !$this->validDeliveryState($deliveryStatus, $sentAt, $failureCode)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
            || $updatedAt < $createdAt
            || ($sentAt instanceof DateTimeImmutable && (
                $sentAt->getOffset() !== 0
                || $sentAt < $createdAt
            ))
        ) {
            throw new InvalidArgumentException('Contact reply data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function contactMessageId(): int { return $this->contactMessageId; }
    public function replyBody(): string { return $this->replyBody; }
    public function sentBy(): int { return $this->sentBy; }
    public function deliveryStatus(): string { return $this->deliveryStatus; }
    public function sentAt(): ?DateTimeImmutable { return $this->sentAt; }
    public function failureCode(): ?string { return $this->failureCode; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function isSent(): bool
    {
        return $this->deliveryStatus === self::STATUS_SENT;
    }

    private function validBody(string $value): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= 5000
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $value) !== 1;
    }

    private function validDeliveryState(
        string $status,
        ?DateTimeImmutable $sentAt,
        ?string $failureCode,
    ): bool {
        if ($status === self::STATUS_PENDING) {
            return $sentAt === null && $failureCode === null;
        }

        if ($status === self::STATUS_SENT) {
            return $sentAt instanceof DateTimeImmutable && $failureCode === null;
        }

        return $sentAt === null
            && is_string($failureCode)
            && preg_match('/^[a-z][a-z0-9_]{0,79}$/D', $failureCode) === 1;
    }
}
