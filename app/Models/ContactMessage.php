<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one retained Guest inquiry and its controlled workflow state. */
final readonly class ContactMessage
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REPLIED,
        self::STATUS_CLOSED,
    ];

    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private ?string $phone,
        private string $subject,
        private string $message,
        private string $status,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || !$this->requiredSingleLine($name, 100)
            || !$this->validEmail($email)
            || !$this->optionalPhone($phone)
            || !$this->requiredSingleLine($subject, 200)
            || !$this->requiredMessage($message)
            || !in_array($status, self::STATUSES, true)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
            || $updatedAt < $createdAt
        ) {
            throw new InvalidArgumentException('Contact message data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function subject(): string { return $this->subject; }
    public function message(): string { return $this->message; }
    public function status(): string { return $this->status; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    private function requiredSingleLine(string $value, int $maximumLength): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumLength
            && preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $value) !== 1;
    }

    private function validEmail(string $value): bool
    {
        return $value !== ''
            && $value === trim($value)
            && strlen($value) <= 150
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function optionalPhone(?string $value): bool
    {
        return $value === null || (
            $value !== ''
            && $value === trim($value)
            && mb_strlen($value, 'UTF-8') <= 20
            && preg_match('/^\+?[0-9][0-9 ()-]*$/D', $value) === 1
        );
    }

    private function requiredMessage(string $value): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= 5000
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $value) !== 1;
    }
}
