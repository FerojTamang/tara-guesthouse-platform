<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable stored Version 1 booking record.
 */
final readonly class Booking
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    public const CURRENCY_NPR = 'NPR';

    private const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
        self::STATUS_CHECKED_OUT,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
    ];

    public function __construct(
        private int $id,
        private string $bookingReference,
        private ?int $userId,
        private string $guestFullName,
        private string $guestEmail,
        private string $guestPhone,
        private DateTimeImmutable $checkIn,
        private DateTimeImmutable $checkOut,
        private int $adults,
        private int $children,
        private string $currency,
        private string $roomAmount,
        private string $totalAmount,
        private string $bookingStatus,
        private ?string $specialRequest,
        private DateTimeImmutable $termsAcceptedAt,
        private ?string $policyVersion,
        private DateTimeImmutable $confirmationDeadlineAt,
        private ?DateTimeImmutable $confirmedAt,
        private ?DateTimeImmutable $checkedInAt,
        private ?DateTimeImmutable $checkedOutAt,
        private ?DateTimeImmutable $cancelledAt,
        private ?string $cancellationReason,
        private ?DateTimeImmutable $rejectedAt,
        private ?string $rejectionReason,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {
        if (
            $id <= 0
            || ($userId !== null && $userId <= 0)
            || $bookingReference === ''
            || $bookingReference !== trim($bookingReference)
            || strlen($bookingReference) > 30
            || $guestFullName === ''
            || $guestPhone === ''
            || filter_var($guestEmail, FILTER_VALIDATE_EMAIL) === false
            || !$this->isStoredDate($checkIn)
            || !$this->isStoredDate($checkOut)
            || $checkOut <= $checkIn
            || $adults < 1
            || $children < 0
            || $currency !== self::CURRENCY_NPR
            || !$this->isPositiveMoney($roomAmount)
            || !$this->isPositiveMoney($totalAmount)
            || !self::isStatus($bookingStatus)
            || !$this->isUtc($termsAcceptedAt)
            || !$this->isUtc($confirmationDeadlineAt)
            || !$this->isNullableUtc($confirmedAt)
            || !$this->isNullableUtc($checkedInAt)
            || !$this->isNullableUtc($checkedOutAt)
            || !$this->isNullableUtc($cancelledAt)
            || !$this->isNullableUtc($rejectedAt)
            || !$this->isUtc($createdAt)
            || !$this->isUtc($updatedAt)
            || !$this->isNullableUtc($deletedAt)
        ) {
            throw new InvalidArgumentException('Booking data is invalid.');
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

    public function bookingReference(): string
    {
        return $this->bookingReference;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function guestFullName(): string
    {
        return $this->guestFullName;
    }

    public function guestEmail(): string
    {
        return $this->guestEmail;
    }

    public function guestPhone(): string
    {
        return $this->guestPhone;
    }

    public function checkIn(): DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function checkOut(): DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function adults(): int
    {
        return $this->adults;
    }

    public function children(): int
    {
        return $this->children;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function roomAmount(): string
    {
        return $this->roomAmount;
    }

    public function totalAmount(): string
    {
        return $this->totalAmount;
    }

    public function bookingStatus(): string
    {
        return $this->bookingStatus;
    }

    public function specialRequest(): ?string
    {
        return $this->specialRequest;
    }

    public function termsAcceptedAt(): DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function policyVersion(): ?string
    {
        return $this->policyVersion;
    }

    public function confirmationDeadlineAt(): DateTimeImmutable
    {
        return $this->confirmationDeadlineAt;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function checkedInAt(): ?DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function checkedOutAt(): ?DateTimeImmutable
    {
        return $this->checkedOutAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function rejectedAt(): ?DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    private function isStoredDate(DateTimeImmutable $date): bool
    {
        return $date->getOffset() === 0 && $date->format('H:i:s.u') === '00:00:00.000000';
    }

    private function isUtc(DateTimeImmutable $date): bool
    {
        return $date->getOffset() === 0;
    }

    private function isNullableUtc(?DateTimeImmutable $date): bool
    {
        return $date === null || $this->isUtc($date);
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) === 1
            && $value !== '0.00';
    }
}
