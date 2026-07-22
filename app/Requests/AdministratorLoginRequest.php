<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AdministratorAuthenticationException;
use LogicException;

/**
 * Holds one validated Administrator login attempt without exposing its password.
 */
final readonly class AdministratorLoginRequest
{
    public const IDENTIFIER_USERNAME = 'username';

    public const IDENTIFIER_EMAIL = 'email';

    private function __construct(
        private string $identifier,
        private string $identifierType,
        #[\SensitiveParameter]
        private string $password,
    ) {
    }

    /**
     * @param array<string, mixed> $policy
     */
    public static function fromInput(
        mixed $identifier,
        #[\SensitiveParameter] mixed $password,
        array $policy,
    ): self {
        self::assertPolicy($policy);

        if (!is_string($identifier)) {
            throw AdministratorAuthenticationException::invalidInput('Login identifier is invalid.');
        }

        if (!is_string($password)) {
            throw AdministratorAuthenticationException::invalidInput('Password is invalid.');
        }

        [$normalizedIdentifier, $identifierType] = self::validateIdentifier($identifier, $policy);

        return new self(
            $normalizedIdentifier,
            $identifierType,
            self::validatePassword($password, $policy),
        );
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function identifierType(): string
    {
        return $this->identifierType;
    }

    public function isEmailIdentifier(): bool
    {
        return $this->identifierType === self::IDENTIFIER_EMAIL;
    }

    public function isUsernameIdentifier(): bool
    {
        return $this->identifierType === self::IDENTIFIER_USERNAME;
    }

    public function password(): string
    {
        return $this->password;
    }

    /** @return array{identifier: string, identifierType: string} */
    public function __debugInfo(): array
    {
        return [
            'identifier' => $this->identifier,
            'identifierType' => $this->identifierType,
        ];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Administrator login request serialization is not supported.');
    }

    /** @param array<mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Administrator login request unserialization is not supported.');
    }

    /** @param array<string, mixed> $policy */
    private static function assertPolicy(array $policy): void
    {
        foreach ([
            'username_min_characters',
            'username_max_characters',
            'identifier_max_bytes',
            'password_max_bytes',
        ] as $key) {
            if (!isset($policy[$key]) || !is_int($policy[$key]) || $policy[$key] <= 0) {
                throw AdministratorAuthenticationException::failure();
            }
        }

        if (
            $policy['username_min_characters'] > $policy['username_max_characters']
            || $policy['username_max_characters'] > 50
            || $policy['identifier_max_bytes'] > 150
            || $policy['password_max_bytes'] > 72
        ) {
            throw AdministratorAuthenticationException::failure();
        }
    }

    /**
     * @param array<string, mixed> $policy
     * @return array{string, string}
     */
    private static function validateIdentifier(string $value, array $policy): array
    {
        if (preg_match('//u', $value) !== 1 || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw AdministratorAuthenticationException::invalidInput('Login identifier is invalid.');
        }

        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);

        if (!is_string($normalized)) {
            throw AdministratorAuthenticationException::invalidInput('Login identifier is invalid.');
        }

        $normalized = strtolower($normalized);

        if (
            $normalized === ''
            || strlen($normalized) > $policy['identifier_max_bytes']
            || preg_match('/^[\x00-\x7F]*$/D', $normalized) !== 1
        ) {
            throw AdministratorAuthenticationException::invalidInput('Login identifier is invalid.');
        }

        if (str_contains($normalized, '@')) {
            if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
                throw AdministratorAuthenticationException::invalidInput('Login identifier is invalid.');
            }

            return [$normalized, self::IDENTIFIER_EMAIL];
        }

        $length = strlen($normalized);

        if (
            $length < $policy['username_min_characters']
            || $length > $policy['username_max_characters']
            || preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])$/D', $normalized) !== 1
        ) {
            throw AdministratorAuthenticationException::invalidInput('Login identifier is invalid.');
        }

        return [$normalized, self::IDENTIFIER_USERNAME];
    }

    /** @param array<string, mixed> $policy */
    private static function validatePassword(string $password, array $policy): string
    {
        if (
            $password === ''
            || preg_match('//u', $password) !== 1
            || preg_match('/\p{C}/u', $password) === 1
            || strlen($password) > $policy['password_max_bytes']
        ) {
            throw AdministratorAuthenticationException::invalidInput('Password is invalid.');
        }

        return $password;
    }
}
