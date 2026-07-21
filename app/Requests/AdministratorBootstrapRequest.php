<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AdministratorBootstrapException;
use LogicException;

/**
 * Holds one validated and normalized initial-Administrator request.
 */
final readonly class AdministratorBootstrapRequest
{
    private function __construct(
        private string $fullName,
        private string $username,
        private string $email,
        private ?string $phone,
        #[\SensitiveParameter]
        private string $password,
    ) {
    }

    /**
     * Validates raw CLI input against the approved tracked policy.
     *
     * @param array<string, mixed> $policy
     */
    public static function fromInput(
        string $fullName,
        string $username,
        string $email,
        ?string $phone,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $passwordConfirmation,
        array $policy,
    ): self {
        self::assertPolicy($policy);

        return new self(
            self::validateFullName($fullName, $policy),
            self::validateUsername($username, $policy),
            self::validateEmail($email, $policy),
            self::validatePhone($phone, $policy),
            self::validatePassword($password, $passwordConfirmation, $policy),
        );
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function password(): string
    {
        return $this->password;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'fullName' => $this->fullName,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Administrator bootstrap request serialization is not supported.');
    }

    /** @param array<mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Administrator bootstrap request unserialization is not supported.');
    }

    /** @param array<string, mixed> $policy */
    private static function assertPolicy(array $policy): void
    {
        foreach ([
            'full_name_min_characters',
            'full_name_max_characters',
            'username_min_characters',
            'username_max_characters',
            'email_max_bytes',
            'phone_max_characters',
            'password_min_characters',
            'password_max_bytes',
        ] as $key) {
            if (!isset($policy[$key]) || !is_int($policy[$key]) || $policy[$key] <= 0) {
                throw AdministratorBootstrapException::failure();
            }
        }
    }

    /** @param array<string, mixed> $policy */
    private static function validateFullName(string $value, array $policy): string
    {
        if (!self::validUtf8($value) || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw AdministratorBootstrapException::usage('Full name is invalid.');
        }

        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);
        $normalized = is_string($normalized) ? preg_replace('/\s+/u', ' ', $normalized) : null;

        if (!is_string($normalized)) {
            throw AdministratorBootstrapException::usage('Full name is invalid.');
        }

        $length = mb_strlen($normalized, 'UTF-8');

        if (
            $length < $policy['full_name_min_characters']
            || $length > $policy['full_name_max_characters']
            || preg_match('/\p{L}/u', $normalized) !== 1
        ) {
            throw AdministratorBootstrapException::usage('Full name is invalid.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $policy */
    private static function validateUsername(string $value, array $policy): string
    {
        $normalized = strtolower(trim($value));
        $length = strlen($normalized);

        if (
            preg_match('/^[\x00-\x7F]*$/D', $normalized) !== 1
            || $length < $policy['username_min_characters']
            || $length > $policy['username_max_characters']
            || preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])$/D', $normalized) !== 1
        ) {
            throw AdministratorBootstrapException::usage('Username is invalid.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $policy */
    private static function validateEmail(string $value, array $policy): string
    {
        $normalized = strtolower(trim($value));

        if (
            preg_match('/^[\x00-\x7F]*$/D', $normalized) !== 1
            || strlen($normalized) > $policy['email_max_bytes']
            || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw AdministratorBootstrapException::usage('Email is invalid.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $policy */
    private static function validatePhone(?string $value, array $policy): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!self::validUtf8($value) || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw AdministratorBootstrapException::usage('Phone is invalid.');
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $normalized);

        if (
            strlen($normalized) > $policy['phone_max_characters']
            || preg_match('/^\+?[0-9 ()-]+$/D', $normalized) !== 1
            || !is_string($digits)
            || strlen($digits) < 7
            || strlen($digits) > 15
        ) {
            throw AdministratorBootstrapException::usage('Phone is invalid.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $policy */
    private static function validatePassword(string $password, string $confirmation, array $policy): string
    {
        if (
            !self::validUtf8($password)
            || preg_match('/\p{C}/u', $password) === 1
            || mb_strlen($password, 'UTF-8') < $policy['password_min_characters']
            || strlen($password) > $policy['password_max_bytes']
        ) {
            throw AdministratorBootstrapException::usage('Password does not meet the approved policy.');
        }

        if (!hash_equals($password, $confirmation)) {
            throw AdministratorBootstrapException::usage('Password confirmation does not match.');
        }

        return $password;
    }

    private static function validUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }
}
