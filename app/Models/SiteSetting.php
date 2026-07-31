<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one immutable allowlisted site setting. */
final readonly class SiteSetting
{
    public const VALUE_TYPES = [
        'string', 'integer', 'boolean', 'email', 'url', 'relative_path', 'json',
    ];

    public function __construct(
        private int $id,
        private string $key,
        private string $value,
        private string $valueType,
        private bool $public,
        private int $updatedBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $updatedBy <= 0
            || preg_match('/^[a-z][a-z0-9]*(?:\.[a-z0-9]+)*$/D', $key) !== 1
            || mb_strlen($key, 'UTF-8') > 150
            || !in_array($valueType, self::VALUE_TYPES, true)
            || !mb_check_encoding($value, 'UTF-8')
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Site setting data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function key(): string { return $this->key; }
    public function value(): string { return $this->value; }
    public function valueType(): string { return $this->valueType; }
    public function isPublic(): bool { return $this->public; }
    public function updatedBy(): int { return $this->updatedBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
}
