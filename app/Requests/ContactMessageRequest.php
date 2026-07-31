<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ContactException;

/** Validates and normalizes one public contact-message submission. */
final readonly class ContactMessageRequest
{
    private function __construct(
        private string $name,
        private string $email,
        private ?string $phone,
        private string $subject,
        private string $message,
    ) {
    }

    public static function fromInput(
        mixed $name,
        mixed $email,
        mixed $phone,
        mixed $subject,
        mixed $message,
    ): self {
        return new self(
            self::singleLine($name, 'Name', 2, 100),
            self::normalizeEmail($email),
            self::normalizePhone($phone),
            self::singleLine($subject, 'Subject', 3, 200),
            self::normalizeMessage($message),
        );
    }

    public function name(): string { return $this->name; }
    public function email(): string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function subject(): string { return $this->subject; }
    public function message(): string { return $this->message; }

    private static function singleLine(
        mixed $value,
        string $label,
        int $minimumLength,
        int $maximumLength,
    ): string {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ContactException::invalidInput($label . ' is required.');
        }

        $normalized = trim($value);
        $length = mb_strlen($normalized, 'UTF-8');

        if (
            $length < $minimumLength
            || $length > $maximumLength
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ContactException::invalidInput($label . ' is invalid.');
        }

        return $normalized;
    }

    private static function normalizeEmail(mixed $value): string
    {
        if (!is_string($value)) {
            throw ContactException::invalidInput('Email is required.');
        }

        $normalized = strtolower(trim($value));

        if (
            $normalized === ''
            || strlen($normalized) > 150
            || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ContactException::invalidInput('Email is invalid.');
        }

        return $normalized;
    }

    private static function normalizePhone(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ContactException::invalidInput('Phone is invalid.');
        }

        $normalized = trim($value);

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > 20
            || preg_match('/^\+?[0-9][0-9 ()-]*$/D', $normalized) !== 1
        ) {
            throw ContactException::invalidInput('Phone is invalid.');
        }

        return $normalized;
    }

    private static function normalizeMessage(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ContactException::invalidInput('Message is required.');
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $length = mb_strlen($normalized, 'UTF-8');

        if (
            $length < 10
            || $length > 5000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ContactException::invalidInput('Message is invalid.');
        }

        return $normalized;
    }
}
