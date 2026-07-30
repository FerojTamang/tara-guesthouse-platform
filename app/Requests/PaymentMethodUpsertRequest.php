<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\PaymentMethodException;

/**
 * Holds one validated manual payment-method create or update request.
 */
final readonly class PaymentMethodUpsertRequest
{
    private function __construct(
        private string $name,
        private ?string $instructions,
        private ?string $accountReference,
        private bool $requiresProof,
        private bool $active,
    ) {
    }

    public static function fromInput(
        mixed $name,
        mixed $instructions,
        mixed $accountReference,
        mixed $requiresProof,
        mixed $active,
    ): self {
        return new self(
            self::requiredSingleLine($name, 'Name', 100),
            self::optionalMultiline($instructions, 'Instructions', 5000),
            self::optionalSingleLine($accountReference, 'Account reference', 255),
            self::explicitBoolean($requiresProof, 'Proof requirement'),
            self::explicitBoolean($active, 'Active state'),
        );
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

    public function requiresProof(): bool
    {
        return $this->requiresProof;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    private static function requiredSingleLine(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): string {
        $normalized = self::singleLine($value, $label);

        if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > $maximumCharacters) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }

    private static function optionalSingleLine(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $normalized = self::singleLine($value, $label);

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized, 'UTF-8') > $maximumCharacters) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }

    private static function singleLine(mixed $value, string $label): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        $normalized = self::trimUnicode($value, $label);

        if (preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }

    private static function optionalMultiline(
        mixed $value,
        string $label,
        int $maximumCharacters,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $normalized = self::trimUnicode($normalized, $label);

        if ($normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > $maximumCharacters
            || preg_match('/(?:[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf})/u', $normalized) === 1
        ) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }

    private static function explicitBoolean(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized === '0' || $normalized === '1') {
                return $normalized === '1';
            }
        }

        throw PaymentMethodException::invalidInput($label . ' is invalid.');
    }

    private static function trimUnicode(string $value, string $label): string
    {
        $normalized = preg_replace('/^\s+|\s+$/u', '', $value);

        if (!is_string($normalized)) {
            throw PaymentMethodException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }
}
