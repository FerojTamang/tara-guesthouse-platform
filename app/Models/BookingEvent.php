<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

/**
 * Represents one immutable append-only booking event record.
 */
final readonly class BookingEvent
{
    public function __construct(
        private int $id,
        private int $bookingId,
        private string $eventType,
        private ?string $fromStatus,
        private ?string $toStatus,
        private ?int $performedBy,
        private ?string $reason,
        private ?string $metadataJson,
        private DateTimeImmutable $createdAt,
    ) {
        if (
            $id <= 0
            || $bookingId <= 0
            || $eventType === ''
            || $eventType !== trim($eventType)
            || ($fromStatus !== null && !Booking::isStatus($fromStatus))
            || ($toStatus !== null && !Booking::isStatus($toStatus))
            || ($performedBy !== null && $performedBy <= 0)
            || !$this->isValidMetadata($metadataJson)
            || $createdAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Booking event data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function fromStatus(): ?string
    {
        return $this->fromStatus;
    }

    public function toStatus(): ?string
    {
        return $this->toStatus;
    }

    public function performedBy(): ?int
    {
        return $this->performedBy;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function metadataJson(): ?string
    {
        return $this->metadataJson;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function isValidMetadata(?string $metadataJson): bool
    {
        if ($metadataJson === null) {
            return true;
        }

        try {
            json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return true;
    }
}
