<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents one immutable configured manual payment method.
 */
final readonly class PaymentMethod
{
    public function __construct(
        private int $id,
        private string $name,
        private ?string $instructions,
        private ?string $accountReference,
        private ?string $qrImagePath,
        private bool $requiresProof,
        private bool $active,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || $name === ''
            || $name !== trim($name)
            || !mb_check_encoding($name, 'UTF-8')
            || mb_strlen($name, 'UTF-8') > 100
            || ($qrImagePath !== null && !$this->isSafeRelativePath($qrImagePath))
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Payment method data is invalid.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function instructions(): ?string
    {
        return $this->instructions;
    }

    public function accountReference(): ?string
    {
        return $this->accountReference;
    }

    public function qrImagePath(): ?string
    {
        return $this->qrImagePath;
    }

    public function requiresProof(): bool
    {
        return $this->requiresProof;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
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
