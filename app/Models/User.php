<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Represents one immutable stored Administrator account record.
 */
final readonly class User
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_LOCKED = 'locked';

    private const ACCOUNT_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_LOCKED,
    ];

    /**
     * Creates a passive user from validated persistence data.
     */
    public function __construct(
        private int $id,
        private Role $role,
        private string $fullName,
        private string $username,
        private string $email,
        private ?string $phone,
        #[\SensitiveParameter]
        private string $passwordHash,
        private ?string $profileImage,
        private ?DateTimeImmutable $lastLoginAt,
        private string $accountStatus,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {
        if (
            $id <= 0
            || $fullName === ''
            || $username === ''
            || $email === ''
            || $passwordHash === ''
            || !in_array($accountStatus, self::ACCOUNT_STATUSES, true)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
            || ($lastLoginAt !== null && $lastLoginAt->getOffset() !== 0)
            || ($deletedAt !== null && $deletedAt->getOffset() !== 0)
        ) {
            throw new InvalidArgumentException('User data is invalid.');
        }
    }

    /**
     * Returns the stored user identifier.
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Returns the associated immutable role.
     */
    public function role(): Role
    {
        return $this->role;
    }

    /**
     * Returns the stored staff name.
     */
    public function fullName(): string
    {
        return $this->fullName;
    }

    /**
     * Returns the stored username without normalization.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Returns the stored email without normalization.
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Returns the optional stored phone value.
     */
    public function phone(): ?string
    {
        return $this->phone;
    }

    /**
     * Returns the stored password hash for a later authentication service.
     */
    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * Returns the optional profile image path.
     */
    public function profileImage(): ?string
    {
        return $this->profileImage;
    }

    /**
     * Returns the last successful login timestamp when recorded.
     */
    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    /**
     * Returns the exact stored account status.
     */
    public function accountStatus(): string
    {
        return $this->accountStatus;
    }

    /**
     * Returns the account creation timestamp.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns the account update timestamp.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Returns the soft-deletion timestamp when present.
     */
    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Determines whether the stored status is active.
     */
    public function isActive(): bool
    {
        return $this->accountStatus === self::STATUS_ACTIVE;
    }

    /**
     * Determines whether the stored status is inactive.
     */
    public function isInactive(): bool
    {
        return $this->accountStatus === self::STATUS_INACTIVE;
    }

    /**
     * Determines whether the stored status is locked.
     */
    public function isLocked(): bool
    {
        return $this->accountStatus === self::STATUS_LOCKED;
    }

    /**
     * Determines whether the record is soft deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Determines whether the associated role is Administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->role->isAdministrator();
    }

    /**
     * Returns non-secret state for diagnostic tools without exposing the password hash.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'fullName' => $this->fullName,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'profileImage' => $this->profileImage,
            'lastLoginAt' => $this->lastLoginAt,
            'accountStatus' => $this->accountStatus,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }

    /**
     * Prevents native serialization from persisting the password hash.
     *
     * @return array<never, never>
     */
    public function __serialize(): array
    {
        throw new LogicException('User serialization is not supported.');
    }

    /**
     * Prevents native unserialization from bypassing validated construction.
     *
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('User unserialization is not supported.');
    }
}
