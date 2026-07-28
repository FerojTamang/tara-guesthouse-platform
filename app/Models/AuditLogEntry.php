<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

/**
 * Represents one append-only operational audit record.
 */
final readonly class AuditLogEntry
{
    public function __construct(
        private ?int $userId,
        private string $eventType,
        private string $entityType,
        private ?int $entityId,
        private string $description,
        private ?string $metadataJson,
        private ?string $ipAddress,
        private ?string $userAgent,
        private DateTimeImmutable $createdAt,
    ) {
        if (
            ($userId !== null && $userId <= 0)
            || $eventType === ''
            || $eventType !== trim($eventType)
            || strlen($eventType) > 80
            || preg_match('/^[a-z][a-z0-9_]*$/D', $eventType) !== 1
            || $entityType === ''
            || $entityType !== trim($entityType)
            || strlen($entityType) > 50
            || preg_match('/^[a-z][a-z0-9_]*$/D', $entityType) !== 1
            || ($entityId !== null && $entityId <= 0)
            || $description === ''
            || $description !== trim($description)
            || mb_strlen($description, 'UTF-8') > 255
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $description) === 1
            || !$this->isValidMetadata($metadataJson)
            || ($ipAddress !== null && filter_var($ipAddress, FILTER_VALIDATE_IP) === false)
            || ($userAgent !== null && (
                $userAgent === ''
                || !mb_check_encoding($userAgent, 'UTF-8')
                || strlen($userAgent) > 255
                || preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1
            ))
            || $createdAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Audit log entry is invalid.');
        }
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): ?int
    {
        return $this->entityId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function metadataJson(): ?string
    {
        return $this->metadataJson;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
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
            $decoded = json_decode(
                $metadataJson,
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return false;
        }

        return is_array($decoded) && !array_is_list($decoded);
    }
}
