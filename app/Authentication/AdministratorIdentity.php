<?php

declare(strict_types=1);

namespace App\Authentication;

use InvalidArgumentException;

/**
 * Represents the minimum trusted Administrator identity stored in a PHP session.
 */
final readonly class AdministratorIdentity
{
    public const ROLE = 'Administrator';

    public const FORMAT_VERSION = 1;

    public function __construct(private int $userId)
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Administrator user ID must be a positive integer.');
        }
    }

    /**
     * Reconstructs an identity only from the exact approved session shape.
     */
    public static function fromSessionData(mixed $data): ?self
    {
        if (
            !is_array($data)
            || count($data) !== 3
            || !array_key_exists('version', $data)
            || !array_key_exists('user_id', $data)
            || !array_key_exists('role', $data)
            || !is_int($data['version'])
            || !is_int($data['user_id'])
            || !is_string($data['role'])
            || $data['version'] !== self::FORMAT_VERSION
            || $data['role'] !== self::ROLE
            || $data['user_id'] <= 0
        ) {
            return null;
        }

        return new self($data['user_id']);
    }

    /**
     * @return array{version: int, user_id: int, role: string}
     */
    public function toSessionData(): array
    {
        return [
            'version' => self::FORMAT_VERSION,
            'user_id' => $this->userId,
            'role' => self::ROLE,
        ];
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function role(): string
    {
        return self::ROLE;
    }

    public function formatVersion(): int
    {
        return self::FORMAT_VERSION;
    }
}
