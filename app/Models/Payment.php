<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable retained manual payment attempt.
 */
final readonly class Payment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REFUNDED = 'refunded';

    public const CURRENCY_NPR = 'NPR';

    private const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUBMITTED,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_REFUNDED,
    ];

    public function __construct(
        private int $id,
        private int $bookingId,
        private int $paymentMethodId,
        private string $currency,
        private string $amount,
        private ?string $transactionReference,
        private ?string $proofPath,
        private ?string $guestNotes,
        private string $paymentStatus,
        private ?DateTimeImmutable $submittedAt,
        private ?DateTimeImmutable $verifiedAt,
        private ?int $verifiedBy,
        private ?DateTimeImmutable $rejectedAt,
        private ?int $rejectedBy,
        private ?string $verificationNote,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $bookingId <= 0
            || $paymentMethodId <= 0
            || $currency !== self::CURRENCY_NPR
            || !$this->isPositiveMoney($amount)
            || ($transactionReference !== null && strlen($transactionReference) > 100)
            || ($proofPath !== null && !$this->isSafeRelativePath($proofPath))
            || !self::isStatus($paymentStatus)
            || !$this->isNullableUtc($submittedAt)
            || !$this->isNullableUtc($verifiedAt)
            || ($verifiedBy !== null && $verifiedBy <= 0)
            || !$this->isNullableUtc($rejectedAt)
            || ($rejectedBy !== null && $rejectedBy <= 0)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Payment data is invalid.');
        }
    }

    public static function isStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function paymentMethodId(): int
    {
        return $this->paymentMethodId;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function transactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function proofPath(): ?string
    {
        return $this->proofPath;
    }

    public function guestNotes(): ?string
    {
        return $this->guestNotes;
    }

    public function paymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function submittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function verifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function verifiedBy(): ?int
    {
        return $this->verifiedBy;
    }

    public function rejectedAt(): ?DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function rejectedBy(): ?int
    {
        return $this->rejectedBy;
    }

    public function verificationNote(): ?string
    {
        return $this->verificationNote;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPending(): bool
    {
        return $this->paymentStatus === self::STATUS_PENDING;
    }

    public function isSubmitted(): bool
    {
        return $this->paymentStatus === self::STATUS_SUBMITTED;
    }

    public function isVerified(): bool
    {
        return $this->paymentStatus === self::STATUS_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->paymentStatus === self::STATUS_REJECTED;
    }

    public function isRefunded(): bool
    {
        return $this->paymentStatus === self::STATUS_REFUNDED;
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }

    private function isNullableUtc(?DateTimeImmutable $value): bool
    {
        return $value === null || $value->getOffset() === 0;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if (
            $path === ''
            || !mb_check_encoding($path, 'UTF-8')
            || mb_strlen($path, 'UTF-8') > 255
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
