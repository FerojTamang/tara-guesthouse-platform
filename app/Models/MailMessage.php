<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use SensitiveParameter;

/** Represents one immutable UTF-8 transactional email. */
final readonly class MailMessage
{
    public function __construct(
        #[SensitiveParameter] private string $recipientAddress,
        #[SensitiveParameter] private string $recipientName,
        #[SensitiveParameter] private string $subject,
        #[SensitiveParameter] private string $textBody,
        #[SensitiveParameter] private string $htmlBody,
    ) {
        if (
            !$this->validAddress($recipientAddress)
            || !$this->validHeaderText($recipientName, 150, true)
            || !$this->validHeaderText($subject, 200, false)
            || !$this->validBody($textBody)
            || !$this->validBody($htmlBody)
        ) {
            throw new InvalidArgumentException('Mail message data is invalid.');
        }
    }

    public function recipientAddress(): string
    {
        return $this->recipientAddress;
    }

    public function recipientName(): string
    {
        return $this->recipientName;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function textBody(): string
    {
        return $this->textBody;
    }

    public function htmlBody(): string
    {
        return $this->htmlBody;
    }

    private function validAddress(string $value): bool
    {
        return $value === trim($value)
            && strlen($value) <= 254
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/[\r\n\x00]/', $value) !== 1;
    }

    private function validHeaderText(string $value, int $maximumCharacters, bool $allowEmpty): bool
    {
        return ($allowEmpty || $value !== '')
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= $maximumCharacters
            && preg_match('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 1;
    }

    private function validBody(string $value): bool
    {
        return $value !== ''
            && mb_check_encoding($value, 'UTF-8')
            && strlen($value) <= 262144
            && !str_contains($value, "\0");
    }
}
